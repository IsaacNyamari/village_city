<?php

namespace App\Livewire\Pages;

use App\Livewire\Crud\ResourcePage;
use App\Models\Transporter;
use App\Support\Options;
use Illuminate\Database\Eloquent\Builder;

class Transporters extends ResourcePage
{
    protected function model(): string
    {
        return Transporter::class;
    }

    protected function title(): string
    {
        return 'Transporters';
    }

    protected function banner(): ?string
    {
        return 'site/delivery';
    }

    protected function module(): string
    {
        return 'logistics';
    }

    protected function description(): string
    {
        return 'Transport businesses and individuals contracted to move produce.';
    }

    protected function query(): Builder
    {
        return Transporter::query()->with('vehicles')->withCount('dispatches');
    }

    protected function searchable(): array
    {
        return ['name', 'contact_person', 'phone'];
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Transporter', 'value' => fn ($r) => $r->name],
            ['label' => 'Contact', 'value' => fn ($r) => trim($r->contact_person.' '.($r->phone ? '· '.$r->phone : ''))],
            ['label' => 'Location', 'value' => fn ($r) => $r->location],
            ['label' => 'Vehicles', 'value' => fn ($r) => $r->vehicles->pluck('registration')->implode(', ')],
            ['label' => 'Dispatches', 'value' => fn ($r) => $r->dispatches_count],
            ['label' => 'Status', 'value' => fn ($r) => $r->status, 'badge' => true],
        ];
    }

    protected function filterDefinitions(): array
    {
        return ['status' => ['label' => 'Status', 'options' => Options::STATUS]];
    }

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Business / person name', 'required' => true, 'rules' => ['required', 'string', 'max:255']],
            'contact_person' => ['label' => 'Contact person', 'rules' => ['nullable', 'string', 'max:255']],
            'phone' => ['label' => 'Phone', 'required' => true, 'rules' => ['required', 'string', 'max:30']],
            'email' => ['label' => 'Email', 'type' => 'email', 'rules' => ['nullable', 'email', 'max:255']],
            'location' => ['label' => 'Location', 'rules' => ['nullable', 'string', 'max:255']],
            'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'default' => 'active', 'options' => Options::STATUS, 'rules' => ['required', 'in:active,inactive']],
            'contract_details' => ['label' => 'Contract details', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:2000']],
        ];
    }
}
