<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Farmer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Procurement;
use App\Models\ProcurementItem;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Support\Options;
use Illuminate\Support\Collection;

/**
 * Builds tabular reports. Each report returns ['columns' => [...], 'rows' => [[...]], 'totals' => ?array].
 * Filters: from, to, county, centre, product, supplier (farmer:ID / supplier:ID), customer, status.
 */
class ReportBuilder
{
    public const REPORTS = [
        'farmers' => 'Farmer registration',
        'suppliers' => 'Supplier performance',
        'procurement' => 'Procurement',
        'product_sales' => 'Product sales',
        'inventory_valuation' => 'Inventory valuation',
        'stock_movement' => 'Stock movement',
        'wastage' => 'Wastage',
        'customer_orders' => 'Customer orders',
        'delivery' => 'Delivery performance',
        'financial' => 'Financial performance',
        'centres' => 'Collection centre performance',
        'counties' => 'County performance',
        'daily_sales' => 'Daily sales',
        'liabilities' => 'Supplier liabilities',
        'receivables' => 'Outstanding customer invoices',
    ];

    /** Which filters each report honours. */
    public const FILTERS = [
        'farmers' => ['from', 'to', 'county', 'centre', 'status'],
        'suppliers' => ['from', 'to', 'county', 'supplier'],
        'procurement' => ['from', 'to', 'county', 'centre', 'product', 'supplier', 'status'],
        'product_sales' => ['from', 'to', 'product', 'customer'],
        'inventory_valuation' => ['centre', 'product'],
        'stock_movement' => ['from', 'to', 'centre', 'product'],
        'wastage' => ['from', 'to', 'centre', 'product'],
        'customer_orders' => ['from', 'to', 'customer', 'status'],
        'delivery' => ['from', 'to', 'status'],
        'financial' => ['from', 'to'],
        'centres' => ['from', 'to', 'county'],
        'counties' => ['from', 'to'],
        'daily_sales' => ['from', 'to'],
        'liabilities' => ['supplier'],
        'receivables' => ['customer'],
    ];

    public function __construct(protected Metrics $metrics) {}

    public function build(string $report, array $f): array
    {
        abort_unless(isset(self::REPORTS[$report]), 404);

        return $this->{'report'.str_replace('_', '', ucwords($report, '_'))}($f);
    }

    protected function dates(array $f): array
    {
        return [filled($f['from'] ?? null) ? \Illuminate\Support\Carbon::parse($f['from']) : null, filled($f['to'] ?? null) ? \Illuminate\Support\Carbon::parse($f['to']) : null];
    }

    protected function source(array $f): array
    {
        [$type, $id] = array_pad(explode(':', (string) ($f['supplier'] ?? '')), 2, null);

        return [$type, $id];
    }

    protected function reportFarmers(array $f): array
    {
        [$from, $to] = $this->dates($f);
        $rows = Farmer::with(['county', 'centre', 'products'])
            ->when($from, fn ($q) => $q->whereDate('registered_at', '>=', $from))->when($to, fn ($q) => $q->whereDate('registered_at', '<=', $to))
            ->when($f['county'] ?? null, fn ($q, $v) => $q->where('county_id', $v))->when($f['centre'] ?? null, fn ($q, $v) => $q->where('collection_centre_id', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->orderBy('number')->get();

        return [
            'columns' => ['Number', 'Name', 'Phone', 'County', 'Sub-county', 'Village', 'Farm size (acres)', 'Centre', 'Commodities', 'Registered', 'Status'],
            'rows' => $rows->map(fn ($r) => [$r->number, $r->name, $r->phone, $r->county?->name, $r->sub_county, $r->village, $r->farm_size_acres, $r->centre?->name, $r->products->pluck('name')->implode(', '), $r->registered_at?->format('Y-m-d'), $r->status])->all(),
            'totals' => ['Farmers' => $rows->count()],
        ];
    }

    protected function reportSuppliers(array $f): array
    {
        [$from, $to] = $this->dates($f);
        [$type, $id] = $this->source($f);
        $rows = collect();

        $lines = fn ($q) => $q->where('status', 'inspected')->when($from, fn ($w) => $w->whereDate('procurement_date', '>=', $from))->when($to, fn ($w) => $w->whereDate('procurement_date', '<=', $to));

        $suppliers = Supplier::with('county')->when($f['county'] ?? null, fn ($q, $v) => $q->where('county_id', $v))->when($type === 'supplier', fn ($q) => $q->where('id', $id))->get()->map(fn ($s) => ['Supplier', $s->business_name, $s->county?->name, $lines(Procurement::where('supplier_id', $s->id))->with('items')->get()]);
        $farmers = $type === 'supplier' ? collect() : Farmer::with('county')->when($f['county'] ?? null, fn ($q, $v) => $q->where('county_id', $v))->when($type === 'farmer', fn ($q) => $q->where('id', $id))->get()->map(fn ($s) => ['Farmer', $s->name, $s->county?->name, $lines(Procurement::where('farmer_id', $s->id))->with('items')->get()]);

        foreach ($suppliers->concat($farmers) as [$kind, $name, $county, $procs]) {
            if ($procs->isEmpty()) {
                continue;
            }

            $items = $procs->flatMap->items;
            $received = (float) $items->sum('received_quantity');
            $ordered = (float) $items->sum('quantity');
            $rows->push([$kind, $name, $county, $procs->count(), round($received, 2), round((float) $items->sum('accepted_quantity'), 2),
                $received > 0 ? round($items->sum('accepted_quantity') / $received * 100, 1).'%' : '—',
                $ordered > 0 ? round(min(1, $received / $ordered) * 100, 1).'%' : '—', round((float) $procs->sum('total_amount'), 2), round((float) $procs->sum(fn ($p) => $p->balance()), 2)]);
        }

        return [
            'columns' => ['Type', 'Name', 'County', 'Procurements', 'Received qty', 'Accepted qty', 'Acceptance rate', 'Delivery reliability', 'Value (KES)', 'Outstanding (KES)'],
            'rows' => $rows->sortByDesc(fn ($r) => $r[8])->values()->all(),
            'totals' => ['Value' => $rows->sum(8), 'Outstanding' => $rows->sum(9)],
        ];
    }

    protected function reportProcurement(array $f): array
    {
        [$from, $to] = $this->dates($f);
        [$type, $id] = $this->source($f);

        $rows = ProcurementItem::with(['procurement.farmer', 'procurement.supplier', 'procurement.centre.county', 'product'])
            ->whereHas('procurement', fn ($p) => $p
                ->when($from, fn ($q) => $q->whereDate('procurement_date', '>=', $from))->when($to, fn ($q) => $q->whereDate('procurement_date', '<=', $to))
                ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->when($f['centre'] ?? null, fn ($q, $v) => $q->where('collection_centre_id', $v))
                ->when($f['county'] ?? null, fn ($q, $v) => $q->whereHas('centre', fn ($c) => $c->where('county_id', $v)))
                ->when($type === 'farmer', fn ($q) => $q->where('farmer_id', $id))->when($type === 'supplier', fn ($q) => $q->where('supplier_id', $id)))
            ->when($f['product'] ?? null, fn ($q, $v) => $q->where('product_id', $v))->latest('id')->get();

        return [
            'columns' => ['Reference', 'Date', 'Source', 'Centre', 'County', 'Commodity', 'Ordered', 'Received', 'Accepted', 'Rejected', 'Grade', 'Unit price', 'Payable (KES)', 'Status', 'Payment'],
            'rows' => $rows->map(fn ($i) => [$i->procurement->reference, $i->procurement->procurement_date->format('Y-m-d'), $i->procurement->sourceName(), $i->procurement->centre->name, $i->procurement->centre->county?->name, $i->product->name,
                (float) $i->quantity, (float) $i->received_quantity, (float) $i->accepted_quantity, (float) $i->rejected_quantity, $i->grade, (float) $i->unit_price, (float) $i->total, $i->procurement->status->label(), $i->procurement->status->value === 'inspected' ? $i->procurement->payment_status : ''])->all(),
            'totals' => ['Accepted qty' => $rows->sum('accepted_quantity'), 'Payable' => $rows->sum('total')],
        ];
    }

    protected function reportProductSales(array $f): array
    {
        [$from, $to] = $this->dates($f);

        $items = \App\Models\OrderItem::with('product.category')
            ->whereIn('order_id', $this->metrics->salesOrders($from, $to)->when($f['customer'] ?? null, fn ($q, $v) => $q->where('customer_id', $v))->select('id'))
            ->when($f['product'] ?? null, fn ($q, $v) => $q->where('product_id', $v))->get()->groupBy('product_id');

        $rows = $items->map(function ($g) {
            $revenue = (float) $g->sum('total');
            $cost = (float) $g->sum(fn ($i) => $i->fulfilled_quantity * $i->unit_cost);

            return [$g->first()->product->name, $g->first()->product->category?->name, $g->first()->product->unit, round((float) $g->sum('quantity'), 2), round((float) $g->sum('fulfilled_quantity'), 2), round($revenue, 2), round($cost, 2), round($revenue - $cost, 2)];
        })->sortByDesc(fn ($r) => $r[5])->values();

        return ['columns' => ['Product', 'Category', 'Unit', 'Quantity ordered', 'Quantity fulfilled', 'Revenue (KES)', 'Cost of stock (KES)', 'Gross profit (KES)'], 'rows' => $rows->all(), 'totals' => ['Revenue' => $rows->sum(5), 'Gross profit' => $rows->sum(7)]];
    }

    protected function reportInventoryValuation(array $f): array
    {
        $rows = StockBatch::with(['product', 'centre'])->where('quantity', '>', 0)
            ->when($f['centre'] ?? null, fn ($q, $v) => $q->where('collection_centre_id', $v))->when($f['product'] ?? null, fn ($q, $v) => $q->where('product_id', $v))
            ->orderBy('product_id')->orderBy('received_at')->get();

        return [
            'columns' => ['Product', 'Location', 'Batch', 'Received', 'Expiry', 'Grade', 'Quantity', 'Unit cost', 'Value (KES)'],
            'rows' => $rows->map(fn ($b) => [$b->product->name, $b->centre->name, $b->batch_number, $b->received_at->format('Y-m-d'), $b->expiry_date?->format('Y-m-d'), $b->grade, (float) $b->quantity, (float) $b->purchase_price, round($b->quantity * $b->purchase_price, 2)])->all(),
            'totals' => ['Total value (at cost)' => round($rows->sum(fn ($b) => $b->quantity * $b->purchase_price), 2)],
        ];
    }

    protected function movementQuery(array $f)
    {
        [$from, $to] = $this->dates($f);

        return StockMovement::with(['product', 'centre', 'batch', 'user'])
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($f['centre'] ?? null, fn ($q, $v) => $q->where('collection_centre_id', $v))->when($f['product'] ?? null, fn ($q, $v) => $q->where('product_id', $v));
    }

    protected function reportStockMovement(array $f): array
    {
        $rows = $this->movementQuery($f)->latest('id')->limit(2000)->get();

        return [
            'columns' => ['Date', 'Type', 'Product', 'Location', 'Batch', 'Quantity', 'Unit cost', 'By', 'Notes'],
            'rows' => $rows->map(fn ($m) => [$m->created_at->format('Y-m-d H:i'), Options::label('MOVEMENT_TYPES', $m->type), $m->product->name, $m->centre->name, $m->batch?->batch_number, (float) $m->quantity, (float) $m->unit_cost, $m->user?->name ?? 'System', $m->notes])->all(),
            'totals' => null,
        ];
    }

    protected function reportWastage(array $f): array
    {
        $rows = $this->movementQuery($f)->whereIn('type', ['damage', 'expiry', 'wastage'])->latest('id')->get();

        return [
            'columns' => ['Date', 'Type', 'Product', 'Location', 'Batch', 'Quantity lost', 'Value at cost (KES)', 'By', 'Reason'],
            'rows' => $rows->map(fn ($m) => [$m->created_at->format('Y-m-d H:i'), Options::label('MOVEMENT_TYPES', $m->type), $m->product->name, $m->centre->name, $m->batch?->batch_number, abs((float) $m->quantity), round(abs($m->quantity) * $m->unit_cost, 2), $m->user?->name, $m->notes])->all(),
            'totals' => ['Value lost' => round($rows->sum(fn ($m) => abs($m->quantity) * $m->unit_cost), 2)],
        ];
    }

    protected function reportCustomerOrders(array $f): array
    {
        [$from, $to] = $this->dates($f);
        $rows = Order::with('customer')
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($f['customer'] ?? null, fn ($q, $v) => $q->where('customer_id', $v))->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->latest('id')->get();

        return [
            'columns' => ['Order', 'Date', 'Customer', 'Type', 'Total (KES)', 'Paid (KES)', 'Balance (KES)', 'Delivery date', 'Status', 'Payment'],
            'rows' => $rows->map(fn ($o) => [$o->reference, $o->created_at->format('Y-m-d'), $o->customer->name, Options::label('CUSTOMER_TYPES', $o->customer->type), (float) $o->total, (float) $o->amount_paid, $o->balance(), $o->expected_delivery_date?->format('Y-m-d'), $o->status->label(), $o->payment_status])->all(),
            'totals' => ['Orders' => $rows->count(), 'Value' => $rows->where('status', '!=', \App\Enums\OrderStatus::Cancelled)->sum('total')],
        ];
    }

    protected function reportDelivery(array $f): array
    {
        [$from, $to] = $this->dates($f);
        $rows = Delivery::with(['order.customer', 'vehicle'])
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->latest('id')->get();

        return [
            'columns' => ['Delivery', 'Order', 'Customer', 'Vehicle', 'Driver', 'Dispatched', 'Expected', 'Delivered', 'On time?', 'Status'],
            'rows' => $rows->map(fn ($d) => [$d->reference, $d->order->reference, $d->order->customer->name, $d->vehicle?->registration, $d->driver_name, $d->dispatched_at?->format('Y-m-d H:i'), $d->expected_at?->format('Y-m-d H:i'), $d->delivered_at?->format('Y-m-d H:i'),
                $d->delivered_at ? ($d->isLate() ? 'Late' : 'Yes') : '', $d->status->label()])->all(),
            'totals' => ['Deliveries' => $rows->count(), 'Delivered' => $rows->where('status', \App\Enums\DeliveryStatus::Delivered)->count()],
        ];
    }

    protected function reportFinancial(array $f): array
    {
        [$from, $to] = $this->dates($f);
        $m = $this->metrics;
        $cash = fn (string $dir) => (float) \App\Models\FinancialTransaction::where('direction', $dir)
            ->when($from, fn ($q) => $q->whereDate('transaction_date', '>=', $from))->when($to, fn ($q) => $q->whereDate('transaction_date', '<=', $to))->sum('amount');

        $rows = [
            ['Revenue (confirmed orders)', $m->revenue($from, $to), 'Includes delivery charges'],
            ['Goods revenue', $m->goodsRevenue($from, $to), 'Excludes delivery charges'],
            ['Cost of stock sold', $m->costOfGoodsSold($from, $to), 'Purchase cost of allocated stock'],
            ['Gross profit', $m->grossProfit($from, $to), 'Goods revenue − cost of stock sold. NOT net profit.'],
            ['Operating expenses', $m->operatingExpenses($from, $to), 'Recorded, non-voided expenses'],
            ['Gross profit less operating expenses', round($m->grossProfit($from, $to) - $m->operatingExpenses($from, $to), 2), 'Operating result before tax and other items'],
            ['Procurement expenditure', $m->procurementSpend($from, $to), 'Payable value of accepted produce'],
            ['Cash received (ledger, net of reversals)', $cash('in'), 'All money-in ledger entries'],
            ['Cash paid out (ledger, net of reversals)', $cash('out'), 'All money-out ledger entries'],
            ['Net cash flow', $cash('in') - $cash('out'), 'Money in − money out'],
            ['Outstanding customer invoices (today)', $m->receivables(), 'Receivables as at today'],
            ['Supplier liabilities (today)', $m->supplierLiabilities(), 'Payables as at today'],
        ];

        return ['columns' => ['Measure', 'Amount (KES)', 'Definition'], 'rows' => array_map(fn ($r) => [$r[0], round($r[1], 2), $r[2]], $rows), 'totals' => null];
    }

    protected function reportCentres(array $f): array
    {
        [$from, $to] = $this->dates($f);
        $rows = \App\Models\CollectionCentre::with('county')->when($f['county'] ?? null, fn ($q, $v) => $q->where('county_id', $v))->get()->map(function ($c) use ($from, $to) {
            $procs = Procurement::where('collection_centre_id', $c->id)->where('status', 'inspected')
                ->when($from, fn ($q) => $q->whereDate('procurement_date', '>=', $from))->when($to, fn ($q) => $q->whereDate('procurement_date', '<=', $to))->with('items')->get();
            $items = $procs->flatMap->items;
            $received = (float) $items->sum('received_quantity');

            return [$c->name, $c->county?->name, $c->farmers()->count(), $procs->count(), round($received, 2), round((float) $items->sum('accepted_quantity'), 2),
                $received > 0 ? round($items->sum('accepted_quantity') / $received * 100, 1).'%' : '—', round((float) $procs->sum('total_amount'), 2), round((float) $c->batches()->where('quantity', '>', 0)->sum('quantity'), 2)];
        });

        return ['columns' => ['Centre', 'County', 'Farmers', 'Procurements', 'Received qty', 'Accepted qty', 'Acceptance rate', 'Procurement value (KES)', 'Stock on hand'], 'rows' => $rows->sortByDesc(fn ($r) => $r[7])->values()->all(), 'totals' => ['Procurement value' => $rows->sum(7)]];
    }

    protected function reportCounties(array $f): array
    {
        [$from, $to] = $this->dates($f);
        $rows = \App\Models\County::withCount(['farmers', 'suppliers', 'centres'])->get()->map(function ($c) use ($from, $to) {
            $items = ProcurementItem::whereHas('procurement', fn ($p) => $p->where('status', 'inspected')
                ->when($from, fn ($q) => $q->whereDate('procurement_date', '>=', $from))->when($to, fn ($q) => $q->whereDate('procurement_date', '<=', $to))
                ->where(fn ($w) => $w->whereHas('farmer', fn ($x) => $x->where('county_id', $c->id))->orWhereHas('supplier', fn ($x) => $x->where('county_id', $c->id))))->get();

            return [$c->name, $c->farmers_count, $c->suppliers_count, $c->centres_count, round((float) $items->sum('accepted_quantity'), 2), round((float) $items->sum('total'), 2)];
        });

        return ['columns' => ['County', 'Farmers', 'Suppliers', 'Centres', 'Accepted qty sourced', 'Procurement value (KES)'], 'rows' => $rows->sortByDesc(fn ($r) => $r[5])->values()->all(), 'totals' => ['Procurement value' => $rows->sum(5)]];
    }

    protected function reportDailySales(array $f): array
    {
        [$from, $to] = $this->dates($f);
        $rows = $this->metrics->salesOrders($from ?? now()->subDays(30), $to)->get()->groupBy(fn ($o) => $o->confirmed_at->format('Y-m-d'))->sortKeys()
            ->map(fn ($g, $day) => [$day, $g->count(), round((float) $g->sum('total'), 2), round((float) $g->sum('amount_paid'), 2)])->values();

        return ['columns' => ['Date', 'Orders', 'Sales (KES)', 'Paid (KES)'], 'rows' => $rows->all(), 'totals' => ['Sales' => $rows->sum(2), 'Orders' => $rows->sum(1)]];
    }

    protected function reportLiabilities(array $f): array
    {
        [$type, $id] = $this->source($f);
        $rows = Procurement::with(['farmer', 'supplier'])->where('status', 'inspected')->whereColumn('amount_paid', '<', 'total_amount')
            ->when($type === 'farmer', fn ($q) => $q->where('farmer_id', $id))->when($type === 'supplier', fn ($q) => $q->where('supplier_id', $id))->oldest('procurement_date')->get();

        return [
            'columns' => ['Procurement', 'Date', 'Payee', 'Owed (KES)', 'Paid (KES)', 'Balance (KES)', 'Days outstanding'],
            'rows' => $rows->map(fn ($p) => [$p->reference, $p->procurement_date->format('Y-m-d'), $p->sourceName(), (float) $p->total_amount, (float) $p->amount_paid, $p->balance(), (int) $p->procurement_date->diffInDays(today())])->all(),
            'totals' => ['Total liability' => round($rows->sum(fn ($p) => $p->balance()), 2)],
        ];
    }

    protected function reportReceivables(array $f): array
    {
        $rows = Invoice::with('customer')->whereIn('status', ['unpaid', 'partial'])->when($f['customer'] ?? null, fn ($q, $v) => $q->where('customer_id', $v))->orderBy('due_date')->get();

        return [
            'columns' => ['Invoice', 'Customer', 'Issued', 'Due', 'Total (KES)', 'Paid (KES)', 'Balance (KES)', 'Days overdue'],
            'rows' => $rows->map(fn ($i) => [$i->number, $i->customer->name, $i->issue_date->format('Y-m-d'), $i->due_date?->format('Y-m-d'), (float) $i->total, (float) $i->amount_paid, $i->balance(), $i->isOverdue() ? (int) $i->due_date->diffInDays(today()) : 0])->all(),
            'totals' => ['Total outstanding' => round($rows->sum(fn ($i) => $i->balance()), 2)],
        ];
    }
}
