<?php

namespace App\Livewire;

use App\Enums\DeliveryStatus;
use App\Livewire\Concerns\Page;
use App\Models\County;
use App\Models\Delivery;
use App\Models\Farmer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Procurement;
use App\Models\ProcurementItem;
use App\Services\Metrics;
use Livewire\Component;

class Dashboard extends Component
{
    use Page;

    public int $days = 30;

    public function mount(): void
    {
        $user = auth()->user();

        // Customers land in the marketplace; the executive dashboard is for staff.
        if (! $user->can('dashboard.view') && $user->can('marketplace.view')) {
            $this->redirectRoute('marketplace', navigate: true);

            return;
        }

        if (! $user->can('dashboard.view') && ! $user->can('marketplace.view')) {
            $this->redirectRoute('help.index', navigate: true);

            return;
        }

        $this->guard('dashboard.view');
    }

    public function render()
    {
        $m = app(Metrics::class);
        $days = in_array($this->days, [7, 30, 90], true) ? $this->days : 30;
        $from = now()->subDays($days - 1)->startOfDay();
        $to = now();

        $sales = $m->series(Order::query()->whereIn('status', \App\Enums\OrderStatus::salesValues()), 'confirmed_at', 'total', $from, $to);
        $spend = $m->series(Procurement::query()->where('status', 'inspected'), 'inspected_at', 'total_amount', $from, $to);

        $byCategory = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('product_categories', 'product_categories.id', '=', 'products.product_category_id')
            ->whereIn('orders.status', \App\Enums\OrderStatus::salesValues())->where('orders.confirmed_at', '>=', $from)
            ->selectRaw('product_categories.name as label, SUM(order_items.total) as value')->groupBy('product_categories.name')->orderByDesc('value')->get();

        $topProducts = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereIn('orders.status', \App\Enums\OrderStatus::salesValues())->where('orders.confirmed_at', '>=', $from)
            ->selectRaw('products.name as label, products.unit, SUM(order_items.quantity) as qty, SUM(order_items.total) as value')->groupBy('products.name', 'products.unit')->orderByDesc('value')->limit(6)->get();

        $orderStatuses = Order::selectRaw('status, COUNT(*) as c')->where('status', '!=', 'draft')->groupBy('status')->pluck('c', 'status');

        $farmerSupply = ProcurementItem::query()
            ->join('procurements', 'procurements.id', '=', 'procurement_items.procurement_id')
            ->join('farmers', 'farmers.id', '=', 'procurements.farmer_id')
            ->where('procurements.status', 'inspected')->where('procurements.procurement_date', '>=', $from->toDateString())
            ->selectRaw('farmers.name as label, SUM(procurement_items.accepted_quantity) as value')->groupBy('farmers.name')->orderByDesc('value')->limit(6)->get();

        $deliveries = Delivery::where('created_at', '>=', $from)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        $counties = County::withCount(['farmers', 'centres', 'suppliers'])->orderByDesc('farmers_count')->limit(6)->get();

        return $this->page('livewire.dashboard', [
            'k' => $m->kpis(),
            'days' => $days,
            'sales' => $sales,
            'spend' => $spend,
            'byCategory' => $byCategory,
            'topProducts' => $topProducts,
            'orderStatuses' => $orderStatuses,
            'farmerSupply' => $farmerSupply,
            'deliveryStatuses' => $deliveries,
            'counties' => $counties,
            'recentOrders' => Order::with('customer')->latest('id')->limit(6)->get(),
            'recentProcurements' => Procurement::with(['farmer', 'supplier'])->latest('id')->limit(6)->get(),
            'newFarmers' => Farmer::with('county')->latest('id')->limit(5)->get(),
            'lowStock' => $m->lowStock()->take(6),
            'pendingSupplier' => Procurement::with(['farmer', 'supplier'])->where('status', 'inspected')->whereColumn('amount_paid', '<', 'total_amount')->oldest('procurement_date')->limit(5)->get(),
            'openInvoices' => Invoice::with('customer')->whereIn('status', ['unpaid', 'partial'])->orderBy('due_date')->limit(5)->get(),
            'activeDeliveries' => Delivery::with(['order.customer'])->whereIn('status', DeliveryStatus::activeValues())->latest('id')->limit(5)->get(),
            'notifications' => auth()->user()->notifications()->limit(5)->get(),
        ], 'Dashboard');
    }
}
