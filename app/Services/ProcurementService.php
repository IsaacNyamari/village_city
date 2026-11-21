<?php

namespace App\Services;

use App\Enums\ProcurementStatus;
use App\Models\Procurement;
use App\Models\QualityInspection;
use App\Support\Options;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProcurementService
{
    public function __construct(protected InventoryService $inventory, protected OrderService $orders) {}

    /**
     * @param  list<array{product_id:int, quantity:float|string, unit_price:float|string}>  $items
     */
    public function save(array $data, array $items, ?Procurement $procurement = null): Procurement
    {
        if ($items === []) {
            throw new RuntimeException('Add at least one commodity to the procurement.');
        }

        return DB::transaction(function () use ($data, $items, $procurement) {
            if ($procurement) {
                if ($procurement->status !== ProcurementStatus::Requested) {
                    throw new RuntimeException('Only requested procurements can be edited.');
                }

                $procurement->update($data);
                $procurement->items()->delete();
            } else {
                $procurement = Procurement::create($data + ['status' => ProcurementStatus::Requested, 'created_by' => auth()->id()]);

                Notifier::send('procurement.view', 'New procurement '.$procurement->reference, 'A procurement request has been created for '.$procurement->centre->name.'.', route('procurement.show', $procurement), 'clipboard-document-list');
            }

            foreach ($items as $line) {
                $procurement->items()->create([
                    'product_id' => $line['product_id'],
                    'quantity' => round((float) $line['quantity'], 2),
                    'unit_price' => round((float) $line['unit_price'], 2),
                ]);
            }

            return $procurement;
        });
    }

    public function confirm(Procurement $procurement): void
    {
        if (! $procurement->farmer_id && ! $procurement->supplier_id) {
            throw new RuntimeException('Assign a farmer or supplier before confirming the purchase.');
        }

        $this->transition($procurement, ProcurementStatus::Confirmed);
    }

    /** @param  array<int, float|string>  $received  item id => quantity delivered to the centre */
    public function receive(Procurement $procurement, array $received): void
    {
        DB::transaction(function () use ($procurement, $received) {
            foreach ($procurement->items as $item) {
                $qty = round((float) ($received[$item->id] ?? 0), 2);

                if ($qty < 0) {
                    throw new RuntimeException('Received quantity cannot be negative.');
                }

                $item->update(['received_quantity' => $qty]);
            }

            if ((float) $procurement->items()->sum('received_quantity') <= 0) {
                throw new RuntimeException('Record the quantity received for at least one commodity.');
            }

            $this->transition($procurement, ProcurementStatus::Received, ['received_at' => now()]);

            Notifier::send('quality.manage', 'Produce awaiting inspection', 'Procurement '.$procurement->reference.' has been received at '.$procurement->centre->name.'.', route('quality.index'), 'clipboard-document-check');
        });
    }

    /**
     * Grade received produce. Only accepted quantities enter saleable stock and become payable.
     *
     * @param  array<int, array{accepted: float|string, grade: string, comments?: ?string, image_path?: ?string}>  $results  keyed by item id
     */
    public function inspect(Procurement $procurement, array $results): void
    {
        DB::transaction(function () use ($procurement, $results) {
            $procurement = Procurement::lockForUpdate()->findOrFail($procurement->id);

            if ($procurement->status !== ProcurementStatus::Received) {
                throw new RuntimeException('Only received procurements can be inspected.');
            }

            $total = 0.0;

            foreach ($procurement->items()->with('product')->get() as $item) {
                $received = (float) $item->received_quantity;

                if ($received <= 0) {
                    continue;
                }

                $result = $results[$item->id] ?? null;

                if (! $result || ! array_key_exists($result['grade'] ?? '', Options::GRADES)) {
                    throw new RuntimeException('Select a quality grade for '.$item->product->name.'.');
                }

                $grade = $result['grade'];
                $accepted = $grade === 'rejected' ? 0.0 : round((float) $result['accepted'], 2);

                if ($accepted < 0 || $accepted > $received) {
                    throw new RuntimeException('Accepted quantity for '.$item->product->name.' must be between 0 and '.$received.'.');
                }

                $lineTotal = round($accepted * (float) $item->unit_price, 2);
                $total += $lineTotal;

                $item->update([
                    'accepted_quantity' => $accepted,
                    'rejected_quantity' => round($received - $accepted, 2),
                    'grade' => $grade,
                    'total' => $lineTotal,
                ]);

                QualityInspection::create([
                    'procurement_id' => $procurement->id,
                    'procurement_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'received_quantity' => $received,
                    'accepted_quantity' => $accepted,
                    'rejected_quantity' => round($received - $accepted, 2),
                    'grade' => $grade,
                    'inspector_id' => auth()->id(),
                    'inspected_at' => now(),
                    'comments' => $result['comments'] ?? null,
                    'image_path' => $result['image_path'] ?? null,
                ]);

                if ($accepted > 0) {
                    $this->inventory->receive($item->product, $procurement->centre, $accepted, (float) $item->unit_price, $grade, $item, null, $procurement);
                }
            }

            $this->transition($procurement, ProcurementStatus::Inspected, [
                'inspected_at' => now(),
                'total_amount' => $total,
                'payment_status' => $total > 0 ? 'unpaid' : 'paid',
            ]);

            if ($total > 0) {
                Notifier::send('settlements.manage', 'Supplier payment due', Options::money($total).' is payable to '.$procurement->sourceName().' for '.$procurement->reference.'.', route('settlements.index'), 'banknotes');
            }

            // New stock may unblock orders that were waiting on procurement.
            $this->orders->allocateAwaiting();
        });
    }

    public function cancel(Procurement $procurement): void
    {
        $this->transition($procurement, ProcurementStatus::Cancelled);
    }

    protected function transition(Procurement $procurement, ProcurementStatus $to, array $extra = []): void
    {
        if (! $procurement->status->canTransitionTo($to)) {
            throw new RuntimeException('A procurement that is "'.$procurement->status->label().'" cannot be moved to "'.$to->label().'".');
        }

        $procurement->update(['status' => $to] + $extra);
    }
}
