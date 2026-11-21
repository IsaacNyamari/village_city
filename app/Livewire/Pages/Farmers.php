<?php

namespace App\Livewire\Pages;

use App\Livewire\Crud\ResourcePage;
use App\Models\CollectionCentre;
use App\Models\County;
use App\Models\Farmer;
use App\Models\Product;
use App\Models\Supplier;
use App\Support\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Farmers extends ResourcePage
{
    protected function model(): string
    {
        return Farmer::class;
    }

    protected function title(): string
    {
        return 'Farmers';
    }

    protected function banner(): ?string
    {
        return 'site/hero-farmer';
    }

    protected function module(): string
    {
        return 'farmers';
    }

    protected function description(): string
    {
        return 'Register and manage farmers, the commodities they grow and the aggregation centre they deliver to.';
    }

    protected function query(): Builder
    {
        return Farmer::query()->with(['county', 'centre', 'products']);
    }

    protected function searchable(): array
    {
        return ['name', 'number', 'phone', 'village'];
    }

    protected function showRoute(): ?string
    {
        return 'farmers.show';
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Farmer', 'value' => fn ($r) => $r->name],
            ['label' => 'Number', 'value' => fn ($r) => $r->number],
            ['label' => 'Phone', 'value' => fn ($r) => $r->phone],
            ['label' => 'County', 'value' => fn ($r) => $r->county?->name],
            ['label' => 'Centre', 'value' => fn ($r) => $r->centre?->name],
            ['label' => 'Commodities', 'value' => fn ($r) => $r->products->pluck('name')->take(3)->implode(', ').($r->products->count() > 3 ? '…' : '')],
            ['label' => 'Status', 'value' => fn ($r) => $r->status, 'badge' => true],
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            'county_id' => ['label' => 'County', 'options' => County::orderBy('name')->pluck('name', 'id')->all()],
            'product' => ['label' => 'Commodity', 'options' => Product::orderBy('name')->pluck('name', 'id')->all(),
                'apply' => fn (Builder $q, $v) => $q->whereHas('products', fn ($p) => $p->where('products.id', $v))],
            'collection_centre_id' => ['label' => 'Centre', 'options' => CollectionCentre::orderBy('name')->pluck('name', 'id')->all()],
            'status' => ['label' => 'Status', 'options' => ['pending' => 'Pending approval'] + Options::STATUS],
        ];
    }

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Full name', 'required' => true, 'rules' => ['required', 'string', 'max:255']],
            'phone' => ['label' => 'Phone number', 'required' => true, 'rules' => ['required', 'string', 'max:30']],
            'email' => ['label' => 'Email', 'type' => 'email', 'rules' => ['nullable', 'email', 'max:255']],
            'national_id' => ['label' => 'National ID (optional)', 'rules' => ['nullable', 'string', 'max:30']],
            'county_id' => ['label' => 'County', 'type' => 'select', 'required' => true, 'options' => County::where('status', 'active')->orderBy('name')->pluck('name', 'id')->all(), 'rules' => ['required', 'exists:counties,id']],
            'sub_county' => ['label' => 'Sub-county', 'rules' => ['nullable', 'string', 'max:100']],
            'ward' => ['label' => 'Ward', 'rules' => ['nullable', 'string', 'max:100']],
            'village' => ['label' => 'Village', 'rules' => ['nullable', 'string', 'max:100']],
            'location' => ['label' => 'Physical location', 'rules' => ['nullable', 'string', 'max:255']],
            'gps_coordinates' => ['label' => 'GPS coordinates (optional)', 'placeholder' => '0.2827, 34.7519', 'rules' => ['nullable', 'string', 'max:60']],
            'farm_size_acres' => ['label' => 'Farm size (acres)', 'type' => 'number', 'rules' => ['nullable', 'numeric', 'min:0']],
            'cooperative_id' => ['label' => 'Farmer group / cooperative', 'type' => 'select', 'options' => Supplier::whereIn('type', array_keys(Options::COOPERATIVE_TYPES))->orderBy('business_name')->pluck('business_name', 'id')->all(), 'rules' => ['nullable', 'exists:suppliers,id']],
            'collection_centre_id' => ['label' => 'Aggregation centre', 'type' => 'select', 'options' => CollectionCentre::where('status', 'active')->orderBy('name')->pluck('name', 'id')->all(), 'rules' => ['nullable', 'exists:collection_centres,id']],
            'registered_at' => ['label' => 'Registration date', 'type' => 'date', 'default' => today()->format('Y-m-d'), 'rules' => ['nullable', 'date']],
            'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'default' => 'active', 'options' => ['pending' => 'Pending approval'] + Options::STATUS, 'rules' => ['required', 'in:pending,active,inactive']],
            'product_ids' => ['label' => 'Main commodities', 'type' => 'checkboxes', 'relation' => 'products', 'options' => Product::where('status', 'active')->orderBy('name')->pluck('name', 'id')->all(), 'rules' => ['array']],
            'notes' => ['label' => 'Notes', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:2000']],
        ];
    }

    protected function rowActions(): array
    {
        return [['label' => 'Approve registration', 'icon' => 'check-circle', 'method' => 'approve', 'show' => fn ($r) => $r->status === 'pending']];
    }

    public function approve(int $id): void
    {
        $this->guard('farmers.manage');
        $farmer = Farmer::findOrFail($id);
        $farmer->update(['status' => 'active']);
        $this->toast($farmer->name.' approved and activated.');
    }

    protected function beforeSave(array $data, ?Model $record): array
    {
        $data['registered_at'] ??= today();

        return $data;
    }
}
