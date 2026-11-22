@php use App\Support\Options; use App\Models\Delivery; @endphp
<div class="space-y-6">
    <x-ui.header image="site/delivery" title="Consignment Tracking" description="Who sold it, who bought it, who inspected it and which vehicle carries it, from the centre to the buyer's door." />

    <div class="w-full sm:w-80"><flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Dispatch, vehicle, driver or parcel code…" clearable /></div>

    @forelse ($rows as $d)
        <x-ui.panel wire:key="dsp-{{ $d->id }}">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-zinc-200 p-4">
                <div>
                    <div class="font-semibold text-zinc-900">{{ $d->reference }} · {{ $d->centre->name }} → {{ $d->destination ?: '—' }}</div>
                    <div class="mt-1 text-sm text-zinc-600">
                        Vehicle: {{ $d->vehicle?->registration ?? 'none' }}{{ $d->vehicle && ! $d->vehicle->isCertified() ? ' (not certified)' : '' }}
                        · Driver: {{ $d->driver_name ?: '—' }} {{ $d->driver_phone }}
                        · Inspector: {{ $d->check?->inspector?->name ?? $d->centre->inspector?->name ?? 'unassigned' }}
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <flux:badge color="{{ $d->check?->passed() ? 'green' : 'amber' }}">{{ $d->check?->passed() ? 'Inspector signed off' : 'Awaiting sign-off' }}</flux:badge>
                    <x-ui.status :value="$d->status" list="DISPATCH_STATUS" />
                </div>
            </div>
            <flux:table class="px-4">
                <flux:table.columns>
                    <flux:table.column>Parcel</flux:table.column>
                    <flux:table.column>Seller(s)</flux:table.column>
                    <flux:table.column>Buyer</flux:table.column>
                    <flux:table.column align="end">Buyer's order total</flux:table.column>
                    <flux:table.column>Last mile</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($d->deliveries as $dl)
                        <flux:table.row :key="$dl->id">
                            <flux:table.cell><a href="{{ route('deliveries.show', $dl) }}" wire:navigate class="font-mono text-forest-700 hover:underline">{{ $dl->parcel_code }}</a></flux:table.cell>
                            <flux:table.cell>{{ $dl->order->procurements->map(fn ($p) => $p->farmer?->name)->filter()->unique()->implode(', ') ?: '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $dl->order->customer->name }}<div class="text-xs text-zinc-500">{{ $dl->order->reference }}</div></flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ Options::money($dl->order->total) }}</flux:table.cell>
                            <flux:table.cell>{{ $dl->last_mile_provider ? (Delivery::LAST_MILE[$dl->last_mile_provider] ?? $dl->last_mile_provider).($dl->rider_name ? ' · '.$dl->rider_name : '') : '—' }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm">{{ $dl->status->label() }}</flux:badge></flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-ui.panel>
    @empty
        <x-ui.empty icon="map-pin" title="No consignments yet" text="Dispatches appear here once created." />
    @endforelse

    {{ $rows->links() }}
</div>
