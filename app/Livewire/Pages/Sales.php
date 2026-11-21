<?php

namespace App\Livewire\Pages;

use App\Enums\OrderStatus;
use App\Livewire\Concerns\Page;
use App\Models\Customer;
use App\Models\Order;
use App\Services\Metrics;
use Livewire\Component;
use Livewire\WithPagination;

class Sales extends Component
{
    use Page, WithPagination;

    public string $from = '';

    public string $to = '';

    public string $customer = '';

    public function mount(): void
    {
        $this->guard('orders.view');
        $this->from = now()->startOfMonth()->format('Y-m-d');
        $this->to = today()->format('Y-m-d');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $m = app(Metrics::class);
        $from = $this->from ? \Illuminate\Support\Carbon::parse($this->from) : null;
        $to = $this->to ? \Illuminate\Support\Carbon::parse($this->to) : null;

        $query = $m->salesOrders($from, $to)->when($this->customer !== '', fn ($q) => $q->where('customer_id', $this->customer));

        return $this->page('livewire.pages.sales', [
            'rows' => (clone $query)->with('customer')->latest('confirmed_at')->paginate(15),
            'revenue' => (float) (clone $query)->sum('total'),
            'orders' => (clone $query)->count(),
            'delivered' => (clone $query)->where('status', OrderStatus::Delivered->value)->count(),
            'goods' => $m->goodsRevenue($from, $to),
            'profit' => $m->grossProfit($from, $to),
            'customers' => Customer::orderBy('name')->pluck('name', 'id'),
        ], 'Sales');
    }
}
