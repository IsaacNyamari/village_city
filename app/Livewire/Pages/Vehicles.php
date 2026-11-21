<?php

namespace App\Livewire\Pages;

use App\Livewire\Crud\ResourcePage;
use App\Models\Transporter;
use App\Models\Vehicle;
use App\Support\Options;
use Illuminate\Database\Eloquent\Builder;

class Vehicles extends ResourcePage
{
    protected function model(): string
    {
        return Vehicle::class;
    }

    protected function title(): string
    {
        return 'Vehicles';
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
        return 'Fleet available for dispatch, with drivers and compliance dates.';
    }

    protected function query(): Builder
    {
        return Vehicle::query()->with('transporter');
    }

    protected function searchable(): array
    {
        return ['registration', 'driver_name', 'driver_phone'];
    }

    protected function hasStatusToggle(): bool
    {
        return false;
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Registration', 'value' => fn ($r) => $r->registration],
            ['label' => 'Type', 'value' => fn ($r) => Options::label('VEHICLE_TYPES', $r->type)],
            ['label' => 'Capacity (kg)', 'value' => fn ($r) => $r->capacity_kg ? Options::qty($r->capacity_kg) : null],
            ['label' => 'Transporter', 'value' => fn ($r) => $r->transporter?->name],
            ['label' => 'Driver', 'value' => fn ($r) => trim($r->driver_name.' '.($r->driver_phone ? '· '.$r->driver_phone : ''))],
            ['label' => 'Insurance expiry', 'value' => fn ($r) => $r->insurance_expiry?->format('d M Y').($r->insurance_expiry?->isPast() ? ' (expired)' : '')],
            ['label' => 'Availability', 'value' => fn ($r) => $r->status, 'badge' => true],
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            'status' => ['label' => 'Availability', 'options' => Options::VEHICLE_STATUS],
            'transporter_id' => ['label' => 'Transporter', 'options' => Transporter::orderBy('name')->pluck('name', 'id')->all()],
        ];
    }

    protected function fields(): array
    {
        return [
            'registration' => ['label' => 'Vehicle registration', 'required' => true, 'placeholder' => 'KDA 123A', 'rules' => fn ($record) => ['required', 'string', 'max:20', 'unique:vehicles,registration,'.($record?->id ?? 'NULL')]],
            'type' => ['label' => 'Vehicle type', 'type' => 'select', 'options' => Options::VEHICLE_TYPES, 'rules' => ['nullable', 'in:'.implode(',', array_keys(Options::VEHICLE_TYPES))]],
            'capacity_kg' => ['label' => 'Capacity (kg)', 'type' => 'number', 'rules' => ['nullable', 'numeric', 'min:0']],
            'transporter_id' => ['label' => 'Transporter', 'type' => 'select', 'options' => Transporter::where('status', 'active')->orderBy('name')->pluck('name', 'id')->all(), 'rules' => ['nullable', 'exists:transporters,id']],
            'driver_name' => ['label' => 'Driver', 'rules' => ['nullable', 'string', 'max:255']],
            'driver_phone' => ['label' => 'Driver phone number', 'rules' => ['nullable', 'string', 'max:30']],
            'insurance_expiry' => ['label' => 'Insurance expiry', 'type' => 'date', 'rules' => ['nullable', 'date']],
            'inspection_expiry' => ['label' => 'Inspection expiry', 'type' => 'date', 'rules' => ['nullable', 'date']],
            'status' => ['label' => 'Availability status', 'type' => 'select', 'required' => true, 'default' => 'available', 'options' => Options::VEHICLE_STATUS, 'rules' => ['required', 'in:'.implode(',', array_keys(Options::VEHICLE_STATUS))]],
        ];
    }
}
