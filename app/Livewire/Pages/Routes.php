<?php

namespace App\Livewire\Pages;

use App\Livewire\Crud\ResourcePage;
use App\Models\DeliveryRoute;
use App\Support\Options;
use Illuminate\Database\Eloquent\Builder;

class Routes extends ResourcePage
{
    protected function model(): string
    {
        return DeliveryRoute::class;
    }

    protected function title(): string
    {
        return 'Routes';
    }

    protected function module(): string
    {
        return 'logistics';
    }

    protected function description(): string
    {
        return 'Standard transport routes with distance and estimated travel time. Figures are entered manually — no live map service is connected.';
    }

    protected function query(): Builder
    {
        return DeliveryRoute::query()->withCount('dispatches');
    }

    protected function searchable(): array
    {
        return ['name', 'origin', 'destination'];
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Route', 'value' => fn ($r) => $r->name],
            ['label' => 'Origin', 'value' => fn ($r) => $r->origin],
            ['label' => 'Destination', 'value' => fn ($r) => $r->destination],
            ['label' => 'Distance', 'value' => fn ($r) => $r->distance_km ? Options::qty($r->distance_km).' km' : null],
            ['label' => 'Est. travel time', 'value' => fn ($r) => $r->estimated_hours ? Options::qty($r->estimated_hours).' h' : null],
            ['label' => 'Dispatches', 'value' => fn ($r) => $r->dispatches_count],
            ['label' => 'Status', 'value' => fn ($r) => $r->status, 'badge' => true],
        ];
    }

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Route name', 'required' => true, 'placeholder' => 'Kakamega – Nairobi', 'rules' => ['required', 'string', 'max:255']],
            'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'default' => 'active', 'options' => Options::STATUS, 'rules' => ['required', 'in:active,inactive']],
            'origin' => ['label' => 'Origin', 'required' => true, 'rules' => ['required', 'string', 'max:255']],
            'destination' => ['label' => 'Destination', 'required' => true, 'rules' => ['required', 'string', 'max:255']],
            'distance_km' => ['label' => 'Distance (km)', 'type' => 'number', 'rules' => ['nullable', 'numeric', 'min:0']],
            'estimated_hours' => ['label' => 'Estimated travel time (hours)', 'type' => 'number', 'rules' => ['nullable', 'numeric', 'min:0']],
        ];
    }
}
