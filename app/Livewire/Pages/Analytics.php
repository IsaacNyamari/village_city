<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\Page;
use App\Models\Order;
use App\Models\Procurement;
use App\Services\IntelligenceService;
use App\Services\Metrics;
use App\Support\Options;
use Livewire\Component;

class Analytics extends Component
{
    use Page;

    public int $months = 6;

    public function mount(): void
    {
        $this->guard('intelligence.view');
    }

    public function render()
    {
        $m = app(Metrics::class);
        $months = in_array($this->months, [3, 6, 12], true) ? $this->months : 6;
        $from = now()->subMonths($months - 1)->startOfMonth();
        $to = now();

        $sales = $m->series(Order::query()->whereIn('status', \App\Enums\OrderStatus::salesValues()), 'confirmed_at', 'total', $from, $to, 'month');
        $spend = $m->series(Procurement::query()->where('status', 'inspected'), 'inspected_at', 'total_amount', $from, $to, 'month');
        $insights = app(IntelligenceService::class)->insights(30);

        return $this->page('livewire.pages.analytics', [
            'months' => $months, 'sales' => $sales, 'spend' => $spend, 'insights' => $insights,
            'revenue' => $m->revenue($from, $to), 'gross' => $m->grossProfit($from, $to),
            'procurement' => $m->procurementSpend($from, $to), 'expenses' => $m->operatingExpenses($from, $to),
            'margin' => $m->goodsRevenue($from, $to) > 0 ? round($m->grossProfit($from, $to) / $m->goodsRevenue($from, $to) * 100, 1) : null,
        ], 'Analytics');
    }
}
