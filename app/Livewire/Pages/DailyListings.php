<?php

namespace App\Livewire\Pages;

use App\Livewire\Crud\ResourcePage;
use App\Models\CollectionCentre;
use App\Models\DailyListing;
use App\Models\Product;
use App\Support\Options;
use Illuminate\Database\Eloquent\Builder;

class DailyListings extends ResourcePage
{
    protected function model(): string
    {
        return DailyListing::class;
    }

    protected function title(): string
    {
        return 'Daily Availability';
    }

    protected function singular(): string
    {
        return 'Listing';
    }

    protected function module(): string
    {
        return 'inventory';
    }

    protected function banner(): ?string
    {
        return 'site/market-crates';
    }

    protected function description(): string
    {
        return 'What each trading centre has on offer today. Listings are opened automatically at 00:30 on market days from harvest schedules; add or adjust them here.';
    }

    protected function searchable(): array
    {
        return [];
    }

    protected function query(): Builder
    {
        return DailyListing::query()->with(['centre', 'product'])->orderByDesc('listing_date');
    }

    protected function headerLinks(): array
    {
        return [['Public market page', route('site.market'), 'globe-alt']];
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Date', 'value' => fn ($r) => $r->listing_date->format('D d M')],
            ['label' => 'Centre', 'value' => fn ($r) => $r->centre->name],
            ['label' => 'Product', 'value' => fn ($r) => $r->product->name],
            ['label' => 'Available', 'value' => fn ($r) => Options::qty($r->quantity_available).' '.$r->product->unit],
            ['label' => 'Price', 'value' => fn ($r) => Options::money($r->unit_price)],
            ['label' => 'Status', 'value' => fn ($r) => $r->status, 'badge' => true],
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            'collection_centre_id' => ['label' => 'Centre', 'options' => CollectionCentre::orderBy('name')->pluck('name', 'id')->all()],
            'status' => ['label' => 'Status', 'options' => ['open' => 'Open', 'closed' => 'Closed']],
            'listing_date' => ['label' => 'When', 'options' => ['today' => 'Today', 'upcoming' => 'Upcoming'], 'apply' => fn ($q, $v) => $v === 'today' ? $q->whereDate('listing_date', today()) : $q->whereDate('listing_date', '>', today())],
        ];
    }

    protected function fields(): array
    {
        return [
            'collection_centre_id' => ['label' => 'Collection centre', 'type' => 'select', 'required' => true, 'options' => CollectionCentre::where('status', 'active')->orderBy('name')->pluck('name', 'id')->all(), 'rules' => ['required', 'exists:collection_centres,id']],
            'product_id' => ['label' => 'Product', 'type' => 'select', 'required' => true, 'options' => Product::where('status', 'active')->orderBy('name')->pluck('name', 'id')->all(), 'rules' => ['required', 'exists:products,id']],
            'listing_date' => ['label' => 'Market date', 'type' => 'date', 'required' => true, 'default' => today()->toDateString(), 'rules' => ['required', 'date']],
            'quantity_available' => ['label' => 'Quantity available', 'type' => 'number', 'required' => true, 'default' => 0, 'rules' => ['required', 'numeric', 'min:0']],
            'unit_price' => ['label' => 'Price per unit (KES)', 'type' => 'number', 'required' => true, 'default' => 0, 'rules' => ['required', 'numeric', 'min:0']],
            'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'default' => 'open', 'options' => ['open' => 'Open', 'closed' => 'Closed'], 'rules' => ['required', 'in:open,closed']],
        ];
    }

    protected function hasStatusToggle(): bool
    {
        return false;
    }
}
