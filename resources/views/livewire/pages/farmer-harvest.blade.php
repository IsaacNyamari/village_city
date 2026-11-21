@php use App\Support\Options; @endphp
<x-ui.panel title="Seasonal harvest schedule" description="Expected harvests help plan procurement.">
    @if ($schedules->isEmpty())
        <x-ui.empty icon="calendar-days" title="No harvests scheduled" text="Record when this farmer expects to harvest each commodity." />
    @else
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-sm">
                <thead><tr class="border-b border-zinc-200 bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500"><th class="px-4 py-2.5">Commodity</th><th class="px-4 py-2.5">Season</th><th class="px-4 py-2.5">Expected date</th><th class="px-4 py-2.5 text-right">Expected quantity</th><th class="px-4 py-2.5">Notes</th><th></th></tr></thead>
                <tbody>
                    @foreach ($schedules as $s)
                        <tr class="border-b border-zinc-100">
                            <td class="px-4 py-2 font-medium">{{ $s->product->name }}</td><td class="px-4 py-2">{{ $s->season ?: '—' }}</td>
                            <td class="px-4 py-2">{{ $s->expected_harvest_date->format('d M Y') }}@if ($s->expected_harvest_date->isPast()) <span class="text-xs text-zinc-500">(past)</span>@endif</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ Options::qty($s->expected_quantity) }} {{ $s->product->unit }}</td><td class="px-4 py-2">{{ $s->notes ?: '—' }}</td>
                            <td class="px-4 py-2 text-right">@can('farmers.manage')<flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteHarvest({{ $s->id }})" wire:confirm="Remove this harvest schedule?" aria-label="Remove" />@endcan</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-ui.panel>

<flux:modal wire:model.self="showHarvest" class="w-full max-w-md">
    <form wire:submit="saveHarvest" class="space-y-4">
        <flux:heading size="lg">Add harvest schedule</flux:heading>
        <flux:select wire:model="form.product_id" label="Commodity *"><flux:select.option value="">— Select —</flux:select.option>@foreach ($products as $id => $name)<flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>@endforeach</flux:select>
        <flux:input wire:model="form.season" label="Season" placeholder="Long rains 2026" />
        <flux:input type="date" wire:model="form.expected_harvest_date" label="Expected harvest date *" />
        <flux:input type="number" step="0.01" min="0" wire:model="form.expected_quantity" label="Expected quantity *" />
        <flux:input wire:model="form.notes" label="Notes" />
        <div class="flex justify-end gap-2"><flux:button type="button" variant="ghost" wire:click="$set('showHarvest', false)">Cancel</flux:button><flux:button type="submit" variant="primary">Save</flux:button></div>
    </form>
</flux:modal>
