<?php

namespace App\Livewire\Pages;

use App\Livewire\Crud\ResourcePage;
use App\Models\County;
use App\Support\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Counties extends ResourcePage
{
    protected function model(): string
    {
        return County::class;
    }

    protected function title(): string
    {
        return 'Counties';
    }

    protected function singular(): string
    {
        return 'County';
    }

    protected function module(): string
    {
        return 'counties';
    }

    protected function description(): string
    {
        return 'Sourcing counties and their sub-counties. Add counties here as Village City expands.';
    }

    protected function query(): Builder
    {
        return County::query()->withCount(['farmers', 'suppliers', 'centres', 'subCounties']);
    }

    protected function searchable(): array
    {
        return ['name', 'code', 'region'];
    }

    protected function columns(): array
    {
        return [
            ['label' => 'County', 'value' => fn ($r) => $r->name],
            ['label' => 'Code', 'value' => fn ($r) => $r->code],
            ['label' => 'Region', 'value' => fn ($r) => $r->region],
            ['label' => 'Sub-counties', 'value' => fn ($r) => $r->sub_counties_count],
            ['label' => 'Farmers', 'value' => fn ($r) => $r->farmers_count],
            ['label' => 'Suppliers', 'value' => fn ($r) => $r->suppliers_count],
            ['label' => 'Centres', 'value' => fn ($r) => $r->centres_count],
            ['label' => 'Sourcing', 'value' => fn ($r) => $r->is_sourcing ? 'Yes' : 'No'],
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
            'name' => ['label' => 'County name', 'required' => true, 'rules' => fn ($record) => ['required', 'string', 'max:100', 'unique:counties,name,'.($record?->id ?? 'NULL')]],
            'code' => ['label' => 'County code', 'placeholder' => '037', 'rules' => ['nullable', 'string', 'max:10']],
            'region' => ['label' => 'Region', 'placeholder' => 'Western', 'rules' => ['nullable', 'string', 'max:100']],
            'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'default' => 'active', 'options' => Options::STATUS, 'rules' => ['required', 'in:active,inactive']],
            'is_sourcing' => ['label' => 'Sourcing county', 'type' => 'switch', 'default' => true, 'rules' => ['boolean']],
            'sub_counties_text' => ['label' => 'Sub-counties (one per line)', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:5000']],
        ];
    }

    protected function fillExtra(Model $record): array
    {
        return ['sub_counties_text' => $record->subCounties()->orderBy('name')->pluck('name')->implode("\n")];
    }

    protected function beforeSave(array $data, ?Model $record): array
    {
        unset($data['sub_counties_text']);

        return $data;
    }

    protected function afterSave(Model $record): void
    {
        $names = collect(preg_split('/\r\n|\r|\n/', (string) ($this->form['sub_counties_text'] ?? '')))
            ->map(fn ($n) => trim($n))->filter()->unique()->values();

        $record->subCounties()->whereNotIn('name', $names)->delete();

        foreach ($names as $name) {
            $record->subCounties()->firstOrCreate(['name' => $name]);
        }
    }
}
