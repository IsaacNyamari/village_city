<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\Farmer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Procurement;
use App\Models\Product;
use App\Models\StockBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Shared, database-driven business figures used by the dashboard, reports and intelligence pages.
 *
 * Definitions:
 *  - Revenue: total of orders that have been confirmed and had stock allocated (processing → delivered).
 *  - Procurement spend: payable value of accepted produce on inspected procurements.
 *  - Gross profit: goods revenue (excluding delivery charges) less the purchase cost of the stock sold.
 *    Operating expenses are NOT deducted — this is not net profit.
 */
class Metrics
{
    public function salesOrders(?Carbon $from = null, ?Carbon $to = null): Builder
    {
        return Order::query()
            ->whereIn('status', OrderStatus::salesValues())
            ->when($from, fn ($q) => $q->where('confirmed_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('confirmed_at', '<=', $to->copy()->endOfDay()));
    }

    public function inspectedProcurements(?Carbon $from = null, ?Carbon $to = null): Builder
    {
        return Procurement::query()
            ->where('status', 'inspected')
            ->when($from, fn ($q) => $q->whereDate('procurement_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('procurement_date', '<=', $to));
    }

    public function revenue(?Carbon $from = null, ?Carbon $to = null): float
    {
        return (float) $this->salesOrders($from, $to)->sum('total');
    }

    public function goodsRevenue(?Carbon $from = null, ?Carbon $to = null): float
    {
        return (float) $this->salesOrders($from, $to)->selectRaw('COALESCE(SUM(subtotal - discount), 0) as v')->value('v');
    }

    public function costOfGoodsSold(?Carbon $from = null, ?Carbon $to = null): float
    {
        return (float) OrderItem::query()
            ->whereIn('order_id', $this->salesOrders($from, $to)->select('id'))
            ->selectRaw('COALESCE(SUM(fulfilled_quantity * unit_cost), 0) as v')->value('v');
    }

    public function grossProfit(?Carbon $from = null, ?Carbon $to = null): float
    {
        return round($this->goodsRevenue($from, $to) - $this->costOfGoodsSold($from, $to), 2);
    }

    public function procurementSpend(?Carbon $from = null, ?Carbon $to = null): float
    {
        return (float) $this->inspectedProcurements($from, $to)->sum('total_amount');
    }

    public function operatingExpenses(?Carbon $from = null, ?Carbon $to = null): float
    {
        return (float) Expense::query()->where('status', 'recorded')
            ->when($from, fn ($q) => $q->whereDate('expense_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('expense_date', '<=', $to))
            ->sum('amount');
    }

    public function receivables(): float
    {
        return (float) Invoice::where('status', '!=', 'void')->selectRaw('COALESCE(SUM(total - amount_paid), 0) as v')->value('v');
    }

    public function supplierLiabilities(): float
    {
        return (float) Procurement::where('status', 'inspected')->selectRaw('COALESCE(SUM(total_amount - amount_paid), 0) as v')->value('v');
    }

    public function stockValue(): float
    {
        return (float) StockBatch::where('quantity', '>', 0)->selectRaw('COALESCE(SUM(quantity * purchase_price), 0) as v')->value('v');
    }

    /** Stock on hand per product id (saleable, non-expired). */
    public function stockByProduct(): Collection
    {
        return StockBatch::query()->where('quantity', '>', 0)
            ->where(fn ($q) => $q->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today()))
            ->selectRaw('product_id, SUM(quantity) as qty')->groupBy('product_id')->pluck('qty', 'product_id')
            ->map(fn ($v) => (float) $v);
    }

    /** Active products at or below their minimum stock level. */
    public function lowStock(): Collection
    {
        $stock = $this->stockByProduct();

        return Product::where('status', 'active')->where('min_stock_level', '>', 0)->orderBy('name')->get()
            ->each(fn (Product $p) => $p->on_hand = $stock[$p->id] ?? 0.0)
            ->filter(fn (Product $p) => $p->on_hand <= (float) $p->min_stock_level)
            ->values();
    }

    public function kpis(): array
    {
        $stock = $this->stockByProduct();

        return [
            'revenue' => $this->revenue(),
            'procurement' => $this->procurementSpend(),
            'gross_profit' => $this->grossProfit(),
            'active_farmers' => Farmer::where('status', 'active')->count(),
            'active_customers' => Customer::where('status', 'active')->count(),
            'total_orders' => Order::where('status', '!=', OrderStatus::Draft)->count(),
            'pending_orders' => Order::whereIn('status', ['pending_confirmation', 'confirmed', 'awaiting_procurement'])->count(),
            'products_in_stock' => $stock->filter(fn ($q) => $q > 0)->count(),
            'active_deliveries' => Delivery::whereIn('status', DeliveryStatus::activeValues())->count(),
            'outstanding' => $this->receivables(),
        ];
    }

    /**
     * Daily (or monthly) totals between two dates, grouped in PHP so it works on any database.
     *
     * @return array{labels: list<string>, values: list<float>}
     */
    public function series(Builder $query, string $dateColumn, string $valueColumn, Carbon $from, Carbon $to, string $unit = 'day'): array
    {
        $format = $unit === 'month' ? 'Y-m' : 'Y-m-d';
        $labelFormat = $unit === 'month' ? 'M Y' : 'd M';

        $rows = $query->whereBetween($dateColumn, [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get([$dateColumn, $valueColumn])
            ->groupBy(fn ($row) => Carbon::parse($row->{$dateColumn})->format($format))
            ->map(fn ($group) => round((float) $group->sum($valueColumn), 2));

        $labels = [];
        $values = [];
        $cursor = $unit === 'month' ? $from->copy()->startOfMonth() : $from->copy()->startOfDay();

        while ($cursor <= $to) {
            $labels[] = $cursor->format($labelFormat);
            $values[] = (float) ($rows[$cursor->format($format)] ?? 0);
            $unit === 'month' ? $cursor->addMonth() : $cursor->addDay();
        }

        return ['labels' => $labels, 'values' => $values];
    }
}
