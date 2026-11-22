<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\Page;
use App\Models\CollectionCentre;
use App\Models\OrderItem;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Demand roll-up: how many customers want each commodity, and who collects what at which centre. */
class Aggregation extends Component
{
    use Page;

    #[Url]
    public string $date = '';

    public bool $paidOnly = false;

    public function mount(): void
    {
        $this->guard('orders.view');
        $this->date = $this->date ?: today()->toDateString();
    }

    public function assign(int $itemId, $centreId): void
    {
        $this->guard('orders.manage');
        OrderItem::whereKey($itemId)->update(['collection_centre_id' => $centreId ?: null]);
        $this->toast('Collection centre updated.');
    }

    public function render()
    {
        $items = OrderItem::query()
            ->with(['order.customer', 'product', 'centre'])
            ->whereHas('order', function ($q) {
                $q->whereNotIn('status', ['draft', 'cancelled', 'delivered'])
                    ->where(fn ($d) => $d->whereDate('expected_delivery_date', $this->date)->orWhere(fn ($n) => $n->whereNull('expected_delivery_date')->whereDate('created_at', $this->date)));

                if ($this->paidOnly) {
                    $q->where('payment_status', 'paid');
                }
            })->get();

        $byCommodity = $items->groupBy('product_id')->map(fn ($rows) => [
            'product' => $rows->first()->product,
            'customers' => $rows->pluck('order.customer_id')->unique()->count(),
            'orders' => $rows->pluck('order_id')->unique()->count(),
            'quantity' => $rows->sum('quantity'),
            'value' => $rows->sum('total'),
        ])->sortByDesc('customers')->values();

        $byCentre = $items->groupBy(fn ($i) => $i->collection_centre_id ?? 0)->map(fn ($rows, $id) => [
            'centre' => $id ? $rows->first()->centre : null,
            'rows' => $rows->groupBy('order.customer_id')->map(fn ($c) => [
                'customer' => $c->first()->order->customer,
                'items' => $c,
                'paid' => $c->every(fn ($i) => $i->order->payment_status === 'paid'),
            ])->values(),
        ])->sortBy(fn ($g) => $g['centre'] ? 0 : 1)->values();

        return $this->page('livewire.pages.aggregation', [
            'byCommodity' => $byCommodity,
            'byCentre' => $byCentre,
            'centres' => CollectionCentre::where('status', 'active')->orderBy('name')->pluck('name', 'id'),
        ], 'Order Aggregation');
    }
}
