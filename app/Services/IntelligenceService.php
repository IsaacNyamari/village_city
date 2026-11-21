<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Delivery;
use App\Models\Farmer;
use App\Models\HarvestSchedule;
use App\Models\Invoice;
use App\Models\OrderItem;
use App\Models\ProcurementItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rule-based operational intelligence computed from historical records.
 *
 * Everything returned here is a SYSTEM ESTIMATE, not verified business data. The class is the
 * single seam where an external AI provider could later be plugged in (see App\Services\Contracts
 * style: swap this binding in the container for a provider-backed implementation).
 */
class IntelligenceService
{
    public function __construct(protected Metrics $metrics) {}

    public function weeks(): int
    {
        return max(2, min(12, (int) Setting::get('forecast_weeks', '4')));
    }

    /** Demand rows: non-cancelled order lines within a window. */
    protected function demandRows(Carbon $from, ?Carbon $to = null): Collection
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotIn('orders.status', [OrderStatus::Cancelled->value, OrderStatus::Draft->value])
            ->where('orders.created_at', '>=', $from)
            ->when($to, fn ($q) => $q->where('orders.created_at', '<=', $to))
            ->get(['order_items.product_id', 'order_items.quantity', 'order_items.fulfilled_quantity', 'order_items.unit_price', 'order_items.unit_cost', 'orders.created_at as ordered_at']);
    }

    /**
     * Weekly demand forecast per product using a moving average adjusted for recent trend
     * and, where a full year of history exists, the same-month seasonal pattern.
     */
    public function forecast(): Collection
    {
        $weeks = $this->weeks();
        $start = now()->startOfDay()->subWeeks($weeks);
        $rows = $this->demandRows($start)->groupBy('product_id');
        $stock = $this->metrics->stockByProduct();
        $incoming = $this->incomingByProduct();
        $seasonal = $this->seasonalFactors();

        return Product::where('status', 'active')->with('category')->orderBy('name')->get()->map(function (Product $product) use ($rows, $weeks, $start, $stock, $incoming, $seasonal) {
            $history = array_fill(0, $weeks, 0.0);

            foreach ($rows[$product->id] ?? [] as $row) {
                $index = min($weeks - 1, (int) floor($start->diffInDays(Carbon::parse($row->ordered_at)) / 7));
                $history[max(0, $index)] += (float) $row->quantity;
            }

            $average = array_sum($history) / $weeks;
            $half = intdiv($weeks, 2);
            $older = array_sum(array_slice($history, 0, $half)) / max(1, $half);
            $recent = array_sum(array_slice($history, -$half)) / max(1, $half);
            $trend = $older > 0 ? ($recent - $older) / $older : ($recent > 0 ? 1.0 : 0.0);
            $trend = max(-1.0, min(1.0, $trend));
            $factor = $seasonal[$product->id] ?? 1.0;
            $forecast = round(max(0, $average * (1 + $trend * 0.5) * $factor), 2);

            $onHand = $stock[$product->id] ?? 0.0;
            $activeWeeks = count(array_filter($history, fn ($v) => $v > 0));

            return (object) [
                'product' => $product,
                'history' => array_map(fn ($v) => round($v, 2), $history),
                'average' => round($average, 2),
                'trend' => round($trend * 100),
                'seasonal_factor' => round($factor, 2),
                'forecast' => $forecast,
                'on_hand' => $onHand,
                'incoming' => $incoming[$product->id] ?? 0.0,
                'days_cover' => $forecast > 0 ? round($onHand / ($forecast / 7), 1) : null,
                'confidence' => $activeWeeks >= max(3, $weeks - 1) ? 'High' : ($activeWeeks >= 2 ? 'Medium' : 'Low'),
            ];
        });
    }

    /** Quantity on open procurements (requested, confirmed or awaiting inspection) per product. */
    public function incomingByProduct(): Collection
    {
        return ProcurementItem::query()
            ->join('procurements', 'procurements.id', '=', 'procurement_items.procurement_id')
            ->whereIn('procurements.status', ['requested', 'confirmed', 'received'])
            ->selectRaw('procurement_items.product_id, SUM(procurement_items.quantity) as qty')
            ->groupBy('procurement_items.product_id')->pluck('qty', 'product_id')->map(fn ($v) => (float) $v);
    }

    /** Same-month-last-year demand relative to that year's monthly average (only with enough history). */
    protected function seasonalFactors(): array
    {
        $rows = $this->demandRows(now()->subMonths(13)->startOfMonth(), now()->subMonths(1)->endOfMonth());

        if ($rows->isEmpty() || Carbon::parse($rows->min('ordered_at'))->gt(now()->subMonths(11))) {
            return [];
        }

        $targetMonth = now()->addWeek()->subYear()->format('Y-m');
        $factors = [];

        foreach ($rows->groupBy('product_id') as $productId => $items) {
            $monthly = $items->groupBy(fn ($r) => Carbon::parse($r->ordered_at)->format('Y-m'))->map(fn ($g) => (float) $g->sum('quantity'));
            $average = $monthly->avg();

            if ($average > 0 && isset($monthly[$targetMonth])) {
                $factors[$productId] = max(0.5, min(1.5, $monthly[$targetMonth] / $average));
            }
        }

        return $factors;
    }

    /** Suggested purchase quantities to cover forecast demand, safety stock and the minimum level. */
    public function recommendations(): Collection
    {
        $safetyDays = max(0, (int) Setting::get('safety_stock_days', '3'));

        return $this->forecast()->map(function ($row) use ($safetyDays) {
            $product = $row->product;
            $target = $row->forecast * (7 + $safetyDays) / 7 + (float) $product->min_stock_level;
            $suggested = round(max(0, $target - $row->on_hand - $row->incoming), 2);

            if ($suggested <= 0) {
                return null;
            }

            $reasons = [];

            if ((float) $product->min_stock_level > 0 && $row->on_hand <= (float) $product->min_stock_level) {
                $reasons[] = 'Stock is at or below the minimum level';
            }

            if ($row->days_cover !== null && $row->days_cover < 7) {
                $reasons[] = 'Likely to run out in about '.max(0, $row->days_cover).' day(s)';
            }

            if ($row->trend >= 15) {
                $reasons[] = 'Demand is increasing (+'.$row->trend.'%)';
            }

            $row->suggested = $suggested;
            $row->estimated_cost = round($suggested * (float) $product->buying_price, 2);
            $row->reasons = $reasons ?: ['Forecast demand exceeds stock on hand'];
            $row->priority = $row->on_hand <= 0 || ($row->days_cover !== null && $row->days_cover < 2) ? 'Urgent' : ($row->days_cover !== null && $row->days_cover < 7 ? 'High' : 'Normal');

            return $row;
        })->filter()->sortBy(fn ($r) => ['Urgent' => 0, 'High' => 1, 'Normal' => 2][$r->priority])->values();
    }

    /**
     * Rank farmers and suppliers for a product. Score (0–100) weights:
     * quality 40, reliability 25, price 20, availability 15.
     */
    public function supplierMatches(Product $product): Collection
    {
        $history = ProcurementItem::query()
            ->join('procurements', 'procurements.id', '=', 'procurement_items.procurement_id')
            ->where('procurement_items.product_id', $product->id)
            ->whereIn('procurements.status', ['inspected'])
            ->get(['procurements.farmer_id', 'procurements.supplier_id', 'procurement_items.quantity', 'procurement_items.received_quantity', 'procurement_items.accepted_quantity', 'procurement_items.unit_price']);

        $upcoming = HarvestSchedule::where('product_id', $product->id)->whereDate('expected_harvest_date', '>=', today())
            ->whereDate('expected_harvest_date', '<=', today()->addDays(45))->get()->groupBy('farmer_id');

        $farmerIds = $history->pluck('farmer_id')->filter()->merge($product->farmers()->pluck('farmers.id'))->merge($upcoming->keys())->unique();
        $supplierIds = $history->pluck('supplier_id')->filter()->merge($product->suppliers()->pluck('suppliers.id'))->unique();

        $score = function (string $type, $party, Collection $lines, bool $lists, float $expected) use ($product) {
            $ordered = (float) $lines->sum('quantity');
            $received = (float) $lines->sum('received_quantity');
            $accepted = (float) $lines->sum('accepted_quantity');
            $quality = $received > 0 ? $accepted / $received : null;
            $reliability = $ordered > 0 ? min(1, $received / $ordered) : null;
            $avgPrice = $lines->isNotEmpty() ? (float) $lines->avg('unit_price') : null;
            $reference = (float) $product->buying_price;
            $priceScore = $avgPrice !== null && $reference > 0 ? max(0, min(1, 1 - (($avgPrice - $reference) / $reference))) : null;

            $points = ($quality ?? 0.5) * 40 + ($reliability ?? 0.5) * 25 + ($priceScore ?? 0.5) * 20 + (($lists ? 0.6 : 0) + ($expected > 0 ? 0.4 : 0)) * 15;

            return (object) [
                'type' => $type,
                'party' => $party,
                'name' => $type === 'Farmer' ? $party->name : $party->business_name,
                'county' => $party->county?->name,
                'transactions' => $lines->count(),
                'quality' => $quality !== null ? round($quality * 100) : null,
                'reliability' => $reliability !== null ? round($reliability * 100) : null,
                'avg_price' => $avgPrice !== null ? round($avgPrice, 2) : null,
                'expected_harvest' => $expected,
                'lists_product' => $lists,
                'score' => (int) round($points),
            ];
        };

        $listedFarmers = $product->farmers()->pluck('farmers.id')->all();
        $listedSuppliers = $product->suppliers()->pluck('suppliers.id')->all();

        $farmers = Farmer::with('county')->whereIn('id', $farmerIds)->where('status', 'active')->get()
            ->map(fn (Farmer $f) => $score('Farmer', $f, $history->where('farmer_id', $f->id), in_array($f->id, $listedFarmers), (float) ($upcoming[$f->id] ?? collect())->sum('expected_quantity')));

        $suppliers = Supplier::with('county')->whereIn('id', $supplierIds)->where('status', 'active')->get()
            ->map(fn (Supplier $s) => $score('Supplier', $s, $history->where('supplier_id', $s->id), in_array($s->id, $listedSuppliers), 0));

        return $farmers->concat($suppliers)->sortByDesc('score')->values();
    }

    /** Headline observations derived from records in the last N days. */
    public function insights(int $days = 30): array
    {
        $from = now()->subDays($days)->startOfDay();
        $rows = $this->demandRows($from);
        $products = Product::pluck('name', 'id');
        $units = Product::pluck('unit', 'id');
        $byProduct = $rows->groupBy('product_id');

        $topSelling = $byProduct->map(fn ($g, $id) => (object) [
            'name' => $products[$id] ?? '—', 'unit' => $units[$id] ?? '',
            'quantity' => (float) $g->sum('quantity'),
            'revenue' => (float) $g->sum(fn ($r) => $r->quantity * $r->unit_price),
        ])->sortByDesc('revenue')->take(5)->values();

        $profitable = $byProduct->map(fn ($g, $id) => (object) [
            'name' => $products[$id] ?? '—',
            'profit' => (float) $g->sum(fn ($r) => $r->fulfilled_quantity * ($r->unit_price - $r->unit_cost)),
            'sold' => (float) $g->sum('fulfilled_quantity'),
        ])->filter(fn ($r) => $r->sold > 0)->sortByDesc('profit')->take(5)->values();

        $stock = $this->metrics->stockByProduct();
        $slowMoving = $stock->filter(fn ($qty, $id) => $qty > 0 && ! $byProduct->has($id))
            ->map(fn ($qty, $id) => (object) ['name' => $products[$id] ?? '—', 'unit' => $units[$id] ?? '', 'on_hand' => $qty])->values()->take(5);

        $wastage = StockMovement::whereIn('type', ['damage', 'expiry', 'wastage'])->where('created_at', '>=', $from)
            ->selectRaw('product_id, SUM(-quantity) as qty, SUM(-quantity * unit_cost) as value')->groupBy('product_id')->get()
            ->map(fn ($r) => (object) ['name' => $products[$r->product_id] ?? '—', 'unit' => $units[$r->product_id] ?? '', 'quantity' => (float) $r->qty, 'value' => (float) $r->value])
            ->sortByDesc('value')->take(5)->values();

        $counties = ProcurementItem::query()
            ->join('procurements', 'procurements.id', '=', 'procurement_items.procurement_id')
            ->join('collection_centres', 'collection_centres.id', '=', 'procurements.collection_centre_id')
            ->join('counties', 'counties.id', '=', 'collection_centres.county_id')
            ->where('procurements.status', 'inspected')->where('procurements.procurement_date', '>=', $from->toDateString())
            ->selectRaw('counties.name, SUM(procurement_items.accepted_quantity) as accepted, SUM(procurement_items.received_quantity) as received, SUM(procurement_items.total) as value')
            ->groupBy('counties.name')->orderByDesc('value')->limit(5)->get()
            ->map(fn ($r) => (object) ['name' => $r->name, 'accepted' => (float) $r->accepted, 'value' => (float) $r->value, 'quality' => $r->received > 0 ? round($r->accepted / $r->received * 100) : null]);

        $deliveries = Delivery::where('created_at', '>=', $from)->get();
        $completed = $deliveries->filter(fn ($d) => $d->delivered_at !== null);
        $late = $deliveries->filter(fn (Delivery $d) => $d->isLate());

        $risks = Invoice::with('customer')->whereIn('status', ['unpaid', 'partial'])->whereDate('due_date', '<', today())->get()
            ->groupBy('customer_id')->map(fn ($g) => (object) [
                'name' => $g->first()->customer->name,
                'invoices' => $g->count(),
                'overdue' => (float) $g->sum(fn (Invoice $i) => $i->balance()),
                'oldest_days' => (int) $g->min('due_date')->diffInDays(today()),
            ])->sortByDesc('overdue')->take(5)->values();

        return [
            'days' => $days,
            'top_selling' => $topSelling,
            'profitable' => $profitable,
            'slow_moving' => $slowMoving,
            'wastage' => $wastage,
            'counties' => $counties,
            'deliveries' => ['total' => $deliveries->count(), 'completed' => $completed->count(), 'late' => $late->count(), 'on_time_rate' => $completed->count() > 0 ? round($completed->reject(fn (Delivery $d) => $d->isLate())->count() / $completed->count() * 100) : null],
            'payment_risks' => $risks,
        ];
    }
}
