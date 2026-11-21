<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ProcurementStatus;
use App\Models\CollectionCentre;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Procurement;
use App\Models\Product;
use App\Support\Options;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderService
{
    public function __construct(protected InventoryService $inventory, protected FinanceService $finance) {}

    /**
     * @param  list<array{product_id:int, quantity:float|string, unit_price?:float|string|null, discount?:float|string|null}>  $items
     */
    public function place(Customer $customer, array $items, array $attributes = [], string $source = 'internal'): Order
    {
        if ($customer->status !== 'active') {
            throw new RuntimeException('This customer account is inactive.');
        }

        if ($items === []) {
            throw new RuntimeException('An order needs at least one item.');
        }

        return DB::transaction(function () use ($customer, $items, $attributes, $source) {
            $order = Order::create([
                'customer_id' => $customer->id,
                'status' => OrderStatus::PendingConfirmation,
                'source' => $source,
                'discount' => max(0, (float) ($attributes['discount'] ?? 0)),
                'delivery_fee' => max(0, (float) ($attributes['delivery_fee'] ?? 0)),
                'delivery_address' => $attributes['delivery_address'] ?? $customer->delivery_address,
                'expected_delivery_date' => $attributes['expected_delivery_date'] ?? null,
                'instructions' => $attributes['instructions'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $line) {
                $product = Product::findOrFail($line['product_id']);
                $quantity = round((float) $line['quantity'], 2);

                if ($product->status !== 'active') {
                    throw new RuntimeException($product->name.' is not currently available.');
                }

                if ($quantity < (float) $product->min_order_qty) {
                    throw new RuntimeException('Minimum order for '.$product->name.' is '.Options::qty($product->min_order_qty).' '.$product->unit.'.');
                }

                // Marketplace customers always pay the listed selling price.
                $price = $source === 'marketplace' || blank($line['unit_price'] ?? null) ? (float) $product->selling_price : (float) $line['unit_price'];
                $discount = $source === 'marketplace' ? 0 : max(0, (float) ($line['discount'] ?? 0));

                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $price,
                    'discount' => $discount,
                    'total' => max(0, round($quantity * $price - $discount, 2)),
                ]);
            }

            $this->recalculate($order);
            $this->guardCredit($customer, $order);

            Notifier::send('orders.manage', 'New order '.$order->reference, $customer->name.' placed an order worth '.Options::money($order->total).'.', route('orders.show', $order), 'shopping-cart');

            return $order;
        });
    }

    public function confirm(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            $this->transition($order, OrderStatus::Confirmed, ['confirmed_at' => now()]);
            $this->finance->createInvoice($order);

            if ($order->customer->user) {
                Notifier::send([], 'Order confirmed', 'Your order '.$order->reference.' has been confirmed.', route('orders.show', $order), 'check-circle', null, $order->customer->user);
            }

            return $this->allocate($order);
        });
    }

    /**
     * Allocate available stock (FIFO) to unfulfilled order lines. Stock is never oversold:
     * shortfalls move the order to "awaiting procurement" and raise a procurement request.
     */
    public function allocate(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if (! in_array($order->status, [OrderStatus::Confirmed, OrderStatus::AwaitingProcurement], true)) {
                throw new RuntimeException('Stock can only be allocated to confirmed orders.');
            }

            $short = false;

            foreach ($order->items()->with('product')->get() as $item) {
                $needed = $item->shortfall();

                if ($needed > 0) {
                    $result = $this->inventory->consume($item->product, $needed, $order, null, 'sale', 'Allocated to order '.$order->reference);

                    if ($result['quantity'] > 0) {
                        $previousCost = (float) $item->fulfilled_quantity * (float) $item->unit_cost;
                        $fulfilled = round((float) $item->fulfilled_quantity + $result['quantity'], 2);
                        $item->update([
                            'fulfilled_quantity' => $fulfilled,
                            'unit_cost' => round(($previousCost + $result['cost']) / $fulfilled, 2),
                        ]);
                    }
                }

                $short = $short || $item->shortfall() > 0;
            }

            if (! $short) {
                $this->transition($order, OrderStatus::Processing);
            } elseif ($order->status === OrderStatus::Confirmed) {
                $this->transition($order, OrderStatus::AwaitingProcurement);
                $this->raiseProcurementRequest($order);
            }

            return $order->refresh();
        });
    }

    /** Try to fulfil orders waiting on stock, oldest first (called when new stock arrives). */
    public function allocateAwaiting(): int
    {
        $completed = 0;

        Order::where('status', OrderStatus::AwaitingProcurement)->orderBy('confirmed_at')->orderBy('id')->get()
            ->each(function (Order $order) use (&$completed) {
                if ($this->allocate($order)->status === OrderStatus::Processing) {
                    $completed++;
                    Notifier::send('orders.manage', 'Order ready for processing', 'Stock is now available for order '.$order->reference.'.', route('orders.show', $order), 'check-circle');
                }
            });

        return $completed;
    }

    /** Proceed with only the quantities that could be fulfilled; totals and invoice are reduced to match. */
    public function proceedPartial(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if ($order->status !== OrderStatus::AwaitingProcurement) {
                throw new RuntimeException('Only orders awaiting procurement can proceed with partial fulfilment.');
            }

            if ((float) $order->items()->sum('fulfilled_quantity') <= 0) {
                throw new RuntimeException('No stock has been allocated to this order yet.');
            }

            foreach ($order->items as $item) {
                $ratio = (float) $item->quantity > 0 ? (float) $item->fulfilled_quantity / (float) $item->quantity : 0;
                $discount = round((float) $item->discount * $ratio, 2);
                $item->update([
                    'quantity' => $item->fulfilled_quantity,
                    'discount' => $discount,
                    'total' => max(0, round((float) $item->fulfilled_quantity * (float) $item->unit_price - $discount, 2)),
                ]);
            }

            $this->recalculate($order);
            $this->finance->syncInvoice($order);
            $this->transition($order, OrderStatus::Processing);

            $order->procurements()->where('status', ProcurementStatus::Requested)->get()
                ->each(fn (Procurement $p) => $p->update(['status' => ProcurementStatus::Cancelled, 'notes' => trim($p->notes.' Cancelled: order proceeded with partial fulfilment.')]));

            return $order->refresh();
        });
    }

    public function markReady(Order $order): Order
    {
        $this->transition($order, OrderStatus::ReadyForDispatch);

        return $order;
    }

    public function cancel(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if ((float) $order->amount_paid > 0) {
                throw new RuntimeException('Reverse the payments recorded against this order before cancelling it.');
            }

            $this->transition($order, OrderStatus::Cancelled);
            $this->inventory->returnOrderStock($order);
            $order->items()->update(['fulfilled_quantity' => 0]);
            $order->invoice()->first()?->update(['status' => 'void']);

            $order->procurements()->where('status', ProcurementStatus::Requested)->get()
                ->each(fn (Procurement $p) => $p->update(['status' => ProcurementStatus::Cancelled]));

            return $order->refresh();
        });
    }

    public function transition(Order $order, OrderStatus $to, array $extra = []): void
    {
        if (! $order->status->canTransitionTo($to)) {
            throw new RuntimeException('An order that is "'.$order->status->label().'" cannot be moved to "'.$to->label().'".');
        }

        $order->update(['status' => $to] + $extra);
    }

    public function recalculate(Order $order): void
    {
        $subtotal = (float) $order->items()->sum('total');
        $discount = min((float) $order->discount, $subtotal);

        $order->update([
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => round($subtotal - $discount + (float) $order->delivery_fee, 2),
        ]);
    }

    protected function guardCredit(Customer $customer, Order $order): void
    {
        $onCredit = str_starts_with((string) $customer->payment_terms, 'net_');

        if ($onCredit && (float) $customer->credit_limit > 0 && $customer->creditExposure() > (float) $customer->credit_limit) {
            throw new RuntimeException('This order would exceed the customer credit limit of '.Options::money($customer->credit_limit).'.');
        }
    }

    protected function raiseProcurementRequest(Order $order): void
    {
        $centre = CollectionCentre::where('status', 'active')->orderByRaw("type = 'warehouse' desc")->orderBy('id')->first();
        $shortItems = $order->items()->with('product')->get()->filter(fn ($i) => $i->shortfall() > 0);

        if ($centre && $shortItems->isNotEmpty()) {
            $procurement = Procurement::create([
                'collection_centre_id' => $centre->id,
                'order_id' => $order->id,
                'procurement_date' => today(),
                'status' => ProcurementStatus::Requested,
                'notes' => 'Auto-generated to cover the stock shortfall on order '.$order->reference.'.',
                'created_by' => auth()->id(),
            ]);

            foreach ($shortItems as $item) {
                $procurement->items()->create([
                    'product_id' => $item->product_id,
                    'quantity' => $item->shortfall(),
                    'unit_price' => $item->product->buying_price,
                ]);
            }
        }

        Notifier::send('procurement.manage', 'Procurement needed for '.$order->reference, 'Stock is insufficient for '.$shortItems->count().' item(s). A procurement request has been raised.', route('procurement.index'), 'truck');
    }
}
