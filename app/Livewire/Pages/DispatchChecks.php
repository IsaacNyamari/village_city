<?php

namespace App\Livewire\Pages;

use App\Livewire\Crud\ResourcePage;
use App\Models\Dispatch;
use App\Models\DispatchCheck;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** The inspector's SOP sign-off for a dispatch. A dispatch cannot depart without a passed check. */
class DispatchChecks extends ResourcePage
{
    protected function model(): string
    {
        return DispatchCheck::class;
    }

    protected function title(): string
    {
        return 'Dispatch Sign-off';
    }

    protected function singular(): string
    {
        return 'Sign-off';
    }

    protected function module(): string
    {
        return 'quality';
    }

    protected function banner(): ?string
    {
        return 'site/delivery';
    }

    protected function description(): string
    {
        return 'Before a load leaves the centre the inspector confirms the cargo matches the orders, is labelled and packed to standard, fully loaded, and that SOPs were followed.';
    }

    protected function searchable(): array
    {
        return [];
    }

    protected function hasStatusToggle(): bool
    {
        return false;
    }

    protected function query(): Builder
    {
        return DispatchCheck::query()->with(['dispatch.centre', 'inspector']);
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Dispatch', 'value' => fn ($r) => $r->dispatch->reference],
            ['label' => 'Centre', 'value' => fn ($r) => $r->dispatch->centre->name],
            ['label' => 'Inspector', 'value' => fn ($r) => $r->inspector?->name],
            ['label' => 'Result', 'value' => fn ($r) => $r->passed() ? 'completed' : 'pending', 'badge' => true],
            ['label' => 'Signed off', 'value' => fn ($r) => $r->signed_off_at?->format('d M H:i')],
        ];
    }

    protected function fields(): array
    {
        $dispatches = Dispatch::with('centre')->whereIn('status', ['scheduled', 'dispatched'])->latest('id')->get()
            ->mapWithKeys(fn ($d) => [$d->id => $d->reference.' · '.$d->centre->name])->all();

        $items = collect(DispatchCheck::ITEMS)->map(fn ($label) => ['label' => $label, 'type' => 'switch', 'default' => false, 'rules' => ['boolean']])->all();

        return [
            'dispatch_id' => ['label' => 'Dispatch', 'type' => 'select', 'required' => true, 'options' => $dispatches, 'rules' => fn ($record) => ['required', 'exists:dispatches,id', 'unique:dispatch_checks,dispatch_id,'.($record?->id ?? 'NULL')]],
        ] + $items + [
            'notes' => ['label' => 'Notes / exceptions', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:1000']],
        ];
    }

    protected function beforeSave(array $data, ?Model $record): array
    {
        $data['inspector_id'] = auth()->id();
        $data['signed_off_at'] = collect(array_keys(DispatchCheck::ITEMS))->every(fn ($k) => $data[$k]) ? now() : null;

        return $data;
    }
}
