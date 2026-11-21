<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\Page;
use App\Models\Product;
use App\Services\IntelligenceService;
use Livewire\Component;

class MarketIntelligence extends Component
{
    use Page;

    public string $product = '';

    public function mount(): void
    {
        $this->guard('intelligence.view');
    }

    public function render()
    {
        $svc = app(IntelligenceService::class);
        $selected = $this->product !== '' ? Product::find($this->product) : null;

        return $this->page('livewire.pages.market-intelligence', [
            'insights' => $svc->insights(30),
            'products' => Product::where('status', 'active')->orderBy('name')->pluck('name', 'id'),
            'selected' => $selected,
            'matches' => $selected ? $svc->supplierMatches($selected) : collect(),
        ], 'Market Intelligence');
    }
}
