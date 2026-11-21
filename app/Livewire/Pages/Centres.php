<?php

namespace App\Livewire\Pages;

use App\Livewire\Crud\ResourcePage;
use App\Models\CollectionCentre;
use App\Models\County;
use App\Models\User;
use App\Support\Options;
use Illuminate\Database\Eloquent\Builder;

class Centres extends ResourcePage
{
    protected function model(): string
    {
        return CollectionCentre::class;
    }

    protected function title(): string
    {
        return 'Collection Centres';
    }

    protected function banner(): ?string
    {
        return 'site/market-crates';
    }

    protected function module(): string
    {
        return 'centres';
    }

    protected function description(): string
    {
        return 'Rural aggregation centres and distribution warehouses. Each location keeps its own stock.';
    }

    protected function query(): Builder
    {
        return CollectionCentre::query()->with(['county', 'manager'])->withCount('farmers')->withSum('batches as stock_on_hand', 'quantity');
    }

    protected function searchable(): array
    {
        return ['name', 'code', 'location'];
    }

    protected function showRoute(): ?string
    {
        return 'centres.show';
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Centre', 'value' => fn ($r) => $r->name],
            ['label' => 'Code', 'value' => fn ($r) => $r->code],
            ['label' => 'Type', 'value' => fn ($r) => Options::label('CENTRE_TYPES', $r->type)],
            ['label' => 'County', 'value' => fn ($r) => $r->county?->name],
            ['label' => 'Manager', 'value' => fn ($r) => $r->manager?->name],
            ['label' => 'Farmers', 'value' => fn ($r) => $r->farmers_count],
            ['label' => 'Stock on hand', 'value' => fn ($r) => Options::qty($r->stock_on_hand)],
            ['label' => 'Status', 'value' => fn ($r) => $r->status, 'badge' => true],
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            'type' => ['label' => 'Type', 'options' => Options::CENTRE_TYPES],
            'county_id' => ['label' => 'County', 'options' => County::orderBy('name')->pluck('name', 'id')->all()],
            'status' => ['label' => 'Status', 'options' => Options::STATUS],
        ];
    }

    protected function fields(): array
    {
        $staff = User::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();

        return [
            'name' => ['label' => 'Centre name', 'required' => true, 'rules' => ['required', 'string', 'max:255']],
            'type' => ['label' => 'Type', 'type' => 'select', 'required' => true, 'default' => 'collection_centre', 'options' => Options::CENTRE_TYPES, 'rules' => ['required', 'in:collection_centre,warehouse']],
            'county_id' => ['label' => 'County', 'type' => 'select', 'options' => County::where('status', 'active')->orderBy('name')->pluck('name', 'id')->all(), 'rules' => ['nullable', 'exists:counties,id']],
            'sub_county' => ['label' => 'Sub-county', 'rules' => ['nullable', 'string', 'max:100']],
            'ward' => ['label' => 'Ward', 'rules' => ['nullable', 'string', 'max:100']],
            'location' => ['label' => 'Physical location', 'rules' => ['nullable', 'string', 'max:255']],
            'manager_id' => ['label' => 'Manager', 'type' => 'select', 'options' => $staff, 'rules' => ['nullable', 'exists:users,id']],
            'contact_phone' => ['label' => 'Contact number', 'rules' => ['nullable', 'string', 'max:30']],
            'capacity_kg' => ['label' => 'Capacity (kg)', 'type' => 'number', 'rules' => ['nullable', 'numeric', 'min:0']],
            'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'default' => 'active', 'options' => Options::STATUS, 'rules' => ['required', 'in:active,inactive']],
            'officer_ids' => ['label' => 'Assigned officers', 'type' => 'checkboxes', 'relation' => 'officers', 'options' => $staff, 'rules' => ['array']],
        ];
    }
}
