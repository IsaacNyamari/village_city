<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\Page;
use App\Models\CollectionCentre;
use App\Services\IntelligenceService;
use Livewire\Component;

class Forecasting extends Component
{
    use Page;

    public function mount(): void
    {
        $this->guard('intelligence.view');
    }

    public function render()
    {
        $svc = app(IntelligenceService::class);
        $forecast = $svc->forecast();

        return $this->page('livewire.pages.forecasting', [
            'weeks' => $svc->weeks(),
            'forecast' => $forecast->filter(fn ($r) => $r->forecast > 0 || $r->on_hand > 0)->sortByDesc('forecast')->values(),
            'recommendations' => $svc->recommendations(),
            'hasHistory' => $forecast->contains(fn ($r) => $r->average > 0),
        ], 'Demand Forecasting');
    }
}
