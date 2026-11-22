<?php

namespace App\Livewire\Site;

use App\Models\DailyListing;
use App\Support\Seo;
use Livewire\Component;

class MarketToday extends Component
{
    public function render()
    {
        $listings = DailyListing::with(['centre.county', 'product'])
            ->whereDate('listing_date', today())->where('status', 'open')->where('quantity_available', '>', 0)
            ->get()->sortBy('centre.name')->groupBy('collection_centre_id');

        return view('livewire.site.market-today', ['groups' => $listings])
            ->layout('components.layouts.site', ['seo' => Seo::page('faq', ['breadcrumbs' => [['Home', Seo::url('/')], ['Market today', null]]]) + ['title' => "Today's market"]]);
    }
}
