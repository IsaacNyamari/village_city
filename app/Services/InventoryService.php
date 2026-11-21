<?php

namespace App\Services;

use App\Models\CollectionCentre;
use App\Models\Order;
use App\Models\ProcurementItem;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Single entry point for every change to stock. Each change writes an immutable StockMovement.
 */
class InventoryService
{
    /** Batches that can be sold: positive balance and not past expiry. */
    public function saleableBatches(int $productId, ?int $centreId = null): Builder
    {
        return StockBatch::query()
            ->where('product_id', $productId)
            ->where('quantity', '>', 0)
            ->where(fn ($q) => $q->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today()))
            ->when($centreId, fn ($q) => $q->where('collection_centre_id', $centreId));
    }

    public function available(int $productId, ?int $centreId = null): float
    {
        return (float) $this->saleableBatches($productId, $centreId)->sum('quantity');
    }

    /** Record accepted produce into saleable stock as a new batch. */
    public function receive(Product $product, CollectionCentre $centre, float $quantity, float $unitCost, ?string $grade = null, ?ProcurementItem $item = null, ?Carbon $receivedAt = null, ?Model $reference = null): StockBatch
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Received quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($product, $centre, $quantity, $unitCost, $grade, $item, $receivedAt, $reference) {
            $receivedAt ??= today();

            $batch = StockBatch::create([
                'product_id' => $product->id,
                'collection_centre_id' => $centre->id,
                'procurement_item_id' => $item?->id,
                'received_at' => $receivedAt,
                'expiry_date' => $product->is_perishable && $product->shelf_life_days ? $receivedAt->copy()->addDays($product->shelf_life_days) : null,
                'initial_quantity' => $quantity,
                'quantity' => $quantity,
                'purchase_price' => $unitCost,
                'grade' => $grade,
            ]);

            $this->move($batch, 'receipt', $quantity, $reference ?? $item?->procurement, 'Produce received into stock');

            return $batch;
        });
    }

    /**
     * Consume stock FIFO (oldest batch first). Never takes more than is available.
     *
     * @return array{quantity: float, cost: float}
     */
    public function consume(Product $product, float $quantity, Model $reference, ?int $centreId = null, string $type = 'sale', ?string $notes = null): array
    {
        return DB::transaction(function () use ($product, $quantity, $reference, $centreId, $type, $notes) {
            $remaining = round($quantity, 2);
            $taken = 0.0;
            $cost = 0.0;

            $batches = $this->saleableBatches($product->id, $centreId)
                ->orderBy('received_at')->orderBy('id')
                ->lockForUpdate()->get();

            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min($remaining, (float) $batch->quantity);
                $batch->decrement('quantity', $take);
                $this->move($batch, $type, -$take, $reference, $notes);

                $remaining = round($remaining - $take, 2);
                $taken += $take;
                $cost += $take * (float) $batch->purchase_price;
            }

            if ($taken > 0) {
                $this->checkLowStock($product);
            }

            return ['quantity' => round($taken, 2), 'cost' => round($cost, 2)];
        });
    }

    /** Return stock consumed for an order back to its original batches. */
    public function returnOrderStock(Order $order, string $notes = 'Order cancelled'): float
    {
        return DB::transaction(function () use ($order, $notes) {
            $net = StockMovement::query()
                ->where('reference_type', $order->getMorphClass())->where('reference_id', $order->id)
                ->whereIn('type', ['sale', 'return'])->whereNotNull('stock_batch_id')
                ->selectRaw('stock_batch_id, SUM(quantity) as qty')->groupBy('stock_batch_id')->get();

            $returned = 0.0;

            foreach ($net as $row) {
                $qty = -1 * (float) $row->qty; // net consumed is negative

                if ($qty <= 0) {
                    continue;
                }

                $batch = StockBatch::lockForUpdate()->find($row->stock_batch_id);
                $batch->increment('quantity', $qty);
                $this->move($batch, 'return', $qty, $order, $notes);
                $returned += $qty;
            }

            return $returned;
        });
    }

    /** Write off damaged, expired or wasted produce from a batch. */
    public function writeOff(StockBatch $batch, float $quantity, string $type, ?string $notes = null): StockMovement
    {
        if (! in_array($type, ['damage', 'expiry', 'wastage'], true)) {
            throw new InvalidArgumentException('Invalid write-off type.');
        }

        return DB::transaction(function () use ($batch, $quantity, $type, $notes) {
            $batch = StockBatch::lockForUpdate()->findOrFail($batch->id);
            $this->guardQuantity($batch, $quantity);
            $batch->decrement('quantity', $quantity);
            $movement = $this->move($batch, $type, -$quantity, null, $notes);
            $this->checkLowStock($batch->product);

            return $movement;
        });
    }

    /** Correct a batch balance up or down after a stock count. */
    public function adjust(StockBatch $batch, float $signedQuantity, ?string $notes = null): StockMovement
    {
        if ($signedQuantity == 0.0) {
            throw new InvalidArgumentException('Adjustment quantity cannot be zero.');
        }

        return DB::transaction(function () use ($batch, $signedQuantity, $notes) {
            $batch = StockBatch::lockForUpdate()->findOrFail($batch->id);

            if ($signedQuantity < 0) {
                $this->guardQuantity($batch, abs($signedQuantity));
            }

            $batch->increment('quantity', $signedQuantity);
            $movement = $this->move($batch, 'adjustment', $signedQuantity, null, $notes);
            $this->checkLowStock($batch->product);

            return $movement;
        });
    }

    /** Move part of a batch to another location, preserving batch attributes. */
    public function transfer(StockBatch $batch, CollectionCentre $destination, float $quantity, ?string $notes = null): StockBatch
    {
        if ($destination->id === $batch->collection_centre_id) {
            throw new InvalidArgumentException('Destination must be different from the current location.');
        }

        return DB::transaction(function () use ($batch, $destination, $quantity, $notes) {
            $batch = StockBatch::lockForUpdate()->findOrFail($batch->id);
            $this->guardQuantity($batch, $quantity);

            $batch->decrement('quantity', $quantity);
            $this->move($batch, 'transfer_out', -$quantity, $destination, $notes ?? 'Transfer to '.$destination->name);

            $new = StockBatch::create([
                'product_id' => $batch->product_id,
                'collection_centre_id' => $destination->id,
                'procurement_item_id' => $batch->procurement_item_id,
                'received_at' => $batch->received_at,
                'expiry_date' => $batch->expiry_date,
                'initial_quantity' => $quantity,
                'quantity' => $quantity,
                'purchase_price' => $batch->purchase_price,
                'grade' => $batch->grade,
            ]);

            $this->move($new, 'transfer_in', $quantity, $batch->centre, $notes ?? 'Transfer from '.$batch->centre->name.' (batch '.$batch->batch_number.')');

            return $new;
        });
    }

    protected function guardQuantity(StockBatch $batch, float $quantity): void
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        if ($quantity > (float) $batch->quantity) {
            throw new RuntimeException('Only '.(float) $batch->quantity.' remaining in batch '.$batch->batch_number.'.');
        }
    }

    protected function move(StockBatch $batch, string $type, float $signedQuantity, ?Model $reference = null, ?string $notes = null): StockMovement
    {
        return StockMovement::create([
            'product_id' => $batch->product_id,
            'collection_centre_id' => $batch->collection_centre_id,
            'stock_batch_id' => $batch->id,
            'type' => $type,
            'quantity' => $signedQuantity,
            'unit_cost' => $batch->purchase_price,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'notes' => $notes,
            'user_id' => auth()->id(),
        ]);
    }

    protected function checkLowStock(Product $product): void
    {
        $onHand = $this->available($product->id);

        if ((float) $product->min_stock_level > 0 && $onHand <= (float) $product->min_stock_level) {
            Notifier::send(
                ['inventory.view', 'procurement.manage'],
                'Low stock: '.$product->name,
                $product->name.' is at '.rtrim(rtrim(number_format($onHand, 2), '0'), '.').' '.$product->unit.' (minimum '.(float) $product->min_stock_level.').',
                route('inventory.index'),
                'exclamation-triangle',
                'low-stock-'.$product->id,
            );
        }
    }
}
