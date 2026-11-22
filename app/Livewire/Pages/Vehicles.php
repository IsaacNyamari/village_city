<?php

namespace App\Livewire\Pages;

use App\Livewire\Crud\ResourcePage;
use App\Models\Transporter;
use App\Models\Vehicle;
use App\Support\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

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
        return 'Fleet available for dispatch. Only certified vehicles can be dispatched.';
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
            ['label' => 'Certification', 'value' => fn ($r) => $r->isCertified() ? 'certified' : ($r->certification_status === 'certified' ? 'expired' : $r->certification_status), 'badge' => true],
            ['label' => 'Availability', 'value' => fn ($r) => $r->status, 'badge' => true],
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            'status' => ['label' => 'Availability', 'options' => Options::VEHICLE_STATUS],
            'certification_status' => ['label' => 'Certification', 'options' => Vehicle::CERT_STATUS],
            'transporter_id' => ['label' => 'Transporter', 'options' => Transporter::orderBy('name')->pluck('name', 'id')->all()],
        ];
    }

    protected function fields(): array
    {
        $checklist = collect(Vehicle::CHECKLIST)->mapWithKeys(fn ($label, $key) => ['chk_'.$key => ['label' => $label, 'type' => 'switch', 'default' => false, 'rules' => ['boolean']]])->all();

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
            'certification_status' => ['label' => 'Certification', 'type' => 'select', 'required' => true, 'default' => 'pending', 'options' => Vehicle::CERT_STATUS, 'help' => 'Certified needs every checklist item ticked.', 'rules' => ['required', 'in:'.implode(',', array_keys(Vehicle::CERT_STATUS))]],
            'certification_expiry' => ['label' => 'Certification expiry', 'type' => 'date', 'help' => 'Blank = one year from certification.', 'rules' => ['nullable', 'date']],
        ] + $checklist;
    }

    protected function beforeSave(array $data, ?Model $record): array
    {
        $checklist = [];

        foreach (array_keys(Vehicle::CHECKLIST) as $key) {
            $checklist[$key] = (bool) ($data['chk_'.$key] ?? false);
            unset($data['chk_'.$key]);
        }

        if ($data['certification_status'] === 'certified') {
            if (in_array(false, $checklist, true)) {
                throw new RuntimeException('Tick every checklist item before certifying a vehicle.');
            }

            if (! $record || $record->certification_status !== 'certified') {
                $data['certified_at'] = today();
                $data['certified_by'] = auth()->id();
            }

            $data['certification_expiry'] ??= today()->addYear();
        }

        $data['certification_checklist'] = $checklist;

        return $data;
    }

    protected function fillExtra(Model $record): array
    {
        return collect(Vehicle::CHECKLIST)->mapWithKeys(fn ($label, $key) => ['chk_'.$key => (bool) ($record->certification_checklist[$key] ?? false)])->all();
    }
}
