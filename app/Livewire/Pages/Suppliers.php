<?php

namespace App\Livewire\Pages;

use App\Livewire\Crud\ResourcePage;
use App\Models\County;
use App\Models\Product;
use App\Models\Supplier;
use App\Support\Options;
use Illuminate\Database\Eloquent\Builder;

class Suppliers extends ResourcePage
{
    /** Types listed on this page; the Cooperatives page narrows this. */
    protected function types(): array
    {
        return Options::SUPPLIER_TYPES;
    }

    protected function model(): string
    {
        return Supplier::class;
    }

    protected function title(): string
    {
        return 'Suppliers';
    }

    protected function banner(): ?string
    {
        return 'site/hero-harvest';
    }

    protected function module(): string
    {
        return 'suppliers';
    }

    protected function description(): string
    {
        return 'Individual suppliers, farmer groups, cooperatives and commercial suppliers of agricultural produce.';
    }

    protected function query(): Builder
    {
        return Supplier::query()->with(['county', 'products'])->withCount('members')->whereIn('type', array_keys($this->types()));
    }

    protected function searchable(): array
    {
        return ['business_name', 'number', 'contact_person', 'phone'];
    }

    protected function showRoute(): ?string
    {
        return 'suppliers.show';
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Business name', 'value' => fn ($r) => $r->business_name],
            ['label' => 'Number', 'value' => fn ($r) => $r->number],
            ['label' => 'Category', 'value' => fn ($r) => Options::label('SUPPLIER_TYPES', $r->type)],
            ['label' => 'Contact', 'value' => fn ($r) => trim($r->contact_person.' '.($r->phone ? '· '.$r->phone : ''))],
            ['label' => 'County', 'value' => fn ($r) => $r->county?->name],
            ['label' => 'Products', 'value' => fn ($r) => $r->products->pluck('name')->take(3)->implode(', ').($r->products->count() > 3 ? '…' : '')],
            ['label' => 'Status', 'value' => fn ($r) => $r->status, 'badge' => true],
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            'type' => ['label' => 'Category', 'options' => $this->types()],
            'county_id' => ['label' => 'County', 'options' => County::orderBy('name')->pluck('name', 'id')->all()],
            'status' => ['label' => 'Status', 'options' => Options::STATUS],
        ];
    }

    protected function fields(): array
    {
        return [
            'business_name' => ['label' => 'Business name', 'required' => true, 'rules' => ['required', 'string', 'max:255']],
            'type' => ['label' => 'Supplier category', 'type' => 'select', 'required' => true, 'default' => array_key_first($this->types()), 'options' => $this->types(), 'rules' => ['required', 'in:'.implode(',', array_keys($this->types()))]],
            'contact_person' => ['label' => 'Contact person', 'rules' => ['nullable', 'string', 'max:255']],
            'phone' => ['label' => 'Phone', 'required' => true, 'rules' => ['required', 'string', 'max:30']],
            'email' => ['label' => 'Email', 'type' => 'email', 'rules' => ['nullable', 'email', 'max:255']],
            'county_id' => ['label' => 'County', 'type' => 'select', 'options' => County::where('status', 'active')->orderBy('name')->pluck('name', 'id')->all(), 'rules' => ['nullable', 'exists:counties,id']],
            'location' => ['label' => 'Location', 'rules' => ['nullable', 'string', 'max:255']],
            'payment_terms' => ['label' => 'Payment terms', 'type' => 'select', 'options' => Options::PAYMENT_TERMS, 'rules' => ['nullable', 'in:'.implode(',', array_keys(Options::PAYMENT_TERMS))]],
            'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'default' => 'active', 'options' => Options::STATUS, 'rules' => ['required', 'in:active,inactive']],
            'product_ids' => ['label' => 'Products supplied', 'type' => 'checkboxes', 'relation' => 'products', 'options' => Product::where('status', 'active')->orderBy('name')->pluck('name', 'id')->all(), 'rules' => ['array']],
            'notes' => ['label' => 'Notes', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:2000']],
        ];
    }
}
