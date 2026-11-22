<?php

namespace App\Livewire\Pages;

use App\Livewire\Crud\ResourcePage;
use App\Models\CollectionCentre;
use App\Models\InspectorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/** Onboarding pipeline for the one quality inspector stationed at each trading centre. */
class Inspectors extends ResourcePage
{
    protected function model(): string
    {
        return InspectorProfile::class;
    }

    protected function title(): string
    {
        return 'Quality Inspectors';
    }

    protected function singular(): string
    {
        return 'Inspector';
    }

    protected function module(): string
    {
        return 'quality';
    }

    protected function banner(): ?string
    {
        return 'site/market-crates';
    }

    protected function description(): string
    {
        return 'Onboard one inspector per trading centre: apply, vet, train, sign the SOPs, then activate. Active inspectors are assigned to their centre and sign off every dispatch.';
    }

    protected function searchable(): array
    {
        return ['national_id', 'phone'];
    }

    protected function hasStatusToggle(): bool
    {
        return false;
    }

    protected function query(): Builder
    {
        return InspectorProfile::query()->with(['user', 'centre']);
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Inspector', 'value' => fn ($r) => $r->user->name],
            ['label' => 'Centre', 'value' => fn ($r) => $r->centre?->name],
            ['label' => 'Phone', 'value' => fn ($r) => $r->phone ?: $r->user->phone],
            ['label' => 'Vetted', 'value' => fn ($r) => $r->vetted_at?->format('d M Y')],
            ['label' => 'Trained', 'value' => fn ($r) => $r->trained_at?->format('d M Y')],
            ['label' => 'SOP signed', 'value' => fn ($r) => $r->sop_signed_at?->format('d M Y')],
            ['label' => 'Daily fee', 'value' => fn ($r) => \App\Support\Options::money($r->daily_fee)],
            ['label' => 'Status', 'value' => fn ($r) => $r->status, 'badge' => true],
        ];
    }

    protected function filterDefinitions(): array
    {
        return ['status' => ['label' => 'Stage', 'options' => InspectorProfile::STATUSES]];
    }

    protected function fields(): array
    {
        return [
            'user_id' => ['label' => 'User account', 'type' => 'select', 'required' => true, 'options' => User::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(), 'rules' => fn ($record) => ['required', 'exists:users,id', 'unique:inspector_profiles,user_id,'.($record?->id ?? 'NULL')]],
            'collection_centre_id' => ['label' => 'Trading centre', 'type' => 'select', 'help' => 'One inspector per centre.', 'options' => CollectionCentre::where('status', 'active')->orderBy('name')->pluck('name', 'id')->all(), 'rules' => fn ($record) => ['nullable', 'exists:collection_centres,id', 'unique:inspector_profiles,collection_centre_id,'.($record?->id ?? 'NULL')]],
            'national_id' => ['label' => 'National ID', 'rules' => ['nullable', 'string', 'max:30']],
            'phone' => ['label' => 'Phone', 'rules' => ['nullable', 'string', 'max:30']],
            'status' => ['label' => 'Onboarding stage', 'type' => 'select', 'required' => true, 'default' => 'applied', 'options' => InspectorProfile::STATUSES, 'help' => 'Active needs vetting, training and a signed SOP, plus a centre.', 'rules' => ['required', 'in:'.implode(',', array_keys(InspectorProfile::STATUSES))]],
            'vetted_at' => ['label' => 'Vetted on', 'type' => 'date', 'rules' => ['nullable', 'date']],
            'trained_at' => ['label' => 'Trained on', 'type' => 'date', 'rules' => ['nullable', 'date']],
            'sop_signed_at' => ['label' => 'SOP signed on', 'type' => 'date', 'rules' => ['nullable', 'date']],
            'daily_fee' => ['label' => 'Daily fee (KES)', 'type' => 'number', 'default' => 0, 'rules' => ['nullable', 'numeric', 'min:0']],
            'notes' => ['label' => 'Notes', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:1000']],
        ];
    }

    protected function beforeSave(array $data, ?Model $record): array
    {
        if ($data['status'] === 'active') {
            if (! $data['vetted_at'] || ! $data['trained_at'] || ! $data['sop_signed_at']) {
                throw new RuntimeException('An inspector must be vetted, trained and have signed the SOP before becoming active.');
            }

            if (! $data['collection_centre_id']) {
                throw new RuntimeException('Assign a trading centre before activating the inspector.');
            }
        }

        $data['daily_fee'] ??= 0;

        return $data;
    }

    protected function afterSave(Model $record): void
    {
        $user = $record->user;
        CollectionCentre::where('inspector_id', $user->id)->when($record->collection_centre_id, fn ($q) => $q->where('id', '!=', $record->collection_centre_id))->update(['inspector_id' => null]);

        if ($record->status === 'active') {
            CollectionCentre::whereKey($record->collection_centre_id)->update(['inspector_id' => $user->id]);
            $user->assignRole('Quality Inspector');
        } else {
            CollectionCentre::where('inspector_id', $user->id)->update(['inspector_id' => null]);
        }
    }
}
