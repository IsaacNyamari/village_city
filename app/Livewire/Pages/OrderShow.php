<?php

namespace App\Livewire\Pages;

use App\Enums\OrderStatus;
use App\Livewire\Concerns\Page;
use App\Models\Order;
use App\Services\Metrics;
use App\Services\OrderService;
use Livewire\Component;

class OrderShow extends Component
{
    use Page;

    public Order $order;

    public function mount(Order $order): void
    {
        $this->guard('orders.access');

        // Customers may only open their own orders.
        if (! auth()->user()->can('orders.view')) {
            abort_unless($order->customer_id === auth()->user()->customer?->id, 403);
        }

        $this->order = $order;
    }

    public function confirm(OrderService $orders): void
    {
        $this->guard('orders.manage');

        if ($this->attempt(fn () => $orders->confirm($this->order))) {
            $this->order->refresh();
            $this->order->status === OrderStatus::Processing
                ? $this->toast('Order confirmed and stock allocated.')
                : $this->toast('Order confirmed. Stock is short — a procurement request has been raised.', 'warning');
        }
    }

    public function allocate(OrderService $orders): void
    {
        $this->guard('orders.manage');

        if ($this->attempt(fn () => $orders->allocate($this->order))) {
            $this->order->refresh();
            $this->order->status === OrderStatus::Processing
                ? $this->toast('Stock allocated. The order is now processing.')
                : $this->toast('Stock is still insufficient for this order.', 'warning');
        }
    }

    public function proceedPartial(OrderService $orders): void
    {
        $this->guard('orders.manage');
        $this->attempt(fn () => $orders->proceedPartial($this->order), 'Order reduced to the allocated quantities and moved to processing.');
    }

    public function markReady(OrderService $orders): void
    {
        $this->guard('orders.manage');
        $this->attempt(fn () => $orders->markReady($this->order), 'Order is ready for dispatch.');
    }

    /** Put this order's items back into the cart (current prices apply) and open the marketplace cart. */
    public function reorder()
    {
        $this->guard('marketplace.view');
        $cart = \App\Support\Cart::all();
        $added = 0;

        foreach ($this->order->items()->with('product')->get() as $item) {
            if ($item->product?->status === 'active') {
                $cart[$item->product_id] = max((float) ($cart[$item->product_id] ?? 0), (float) $item->quantity);
                $added++;
            }
        }

        if ($added === 0) {
            $this->toast('None of these products are available right now.', 'danger');

            return null;
        }

        \App\Support\Cart::put($cart);

        return $this->redirectRoute('marketplace', ['cart' => 1], navigate: true);
    }

    public function cancel(OrderService $orders): void
    {
        // Customers can cancel their own order only while it awaits confirmation.
        if (! auth()->user()->can('orders.manage')) {
            abort_unless($this->order->status === OrderStatus::PendingConfirmation && $this->order->customer_id === auth()->user()->customer?->id, 403);
        }

        $this->attempt(fn () => $orders->cancel($this->order), 'Order cancelled.');
    }

    public function render()
    {
        $this->order->refresh()->load(['customer', 'items.product', 'payments', 'deliveries.events', 'deliveries.vehicle', 'procurements', 'creator']);
        $staff = auth()->user()->can('orders.view');

        // Progress steps shown to everyone (the "awaiting procurement" branch replaces "processing" while it applies).
        $steps = [OrderStatus::PendingConfirmation, OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::ReadyForDispatch, OrderStatus::Dispatched, OrderStatus::InTransit, OrderStatus::Delivered];
        $position = array_search($this->order->status === OrderStatus::AwaitingProcurement ? OrderStatus::Confirmed : $this->order->status, $steps, true);

        return $this->page('livewire.pages.order-show', [
            'staff' => $staff,
            'steps' => $steps,
            'position' => $position === false ? -1 : $position,
            'stock' => $staff ? app(Metrics::class)->stockByProduct() : collect(),
            'invoice' => $this->order->invoice()->first(),
            'canCancel' => auth()->user()->can('orders.manage')
                ? $this->order->status->canTransitionTo(OrderStatus::Cancelled)
                : $this->order->status === OrderStatus::PendingConfirmation,
        ], 'Order '.$this->order->reference);
    }
}
