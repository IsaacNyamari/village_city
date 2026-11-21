<div class="space-y-6">
    <x-ui.header image="site/delivery" title="Deliveries" description="One delivery per order on a dispatch. Status changes are recorded manually by the logistics team — no live GPS tracking is connected." />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat label="Active deliveries" :value="$counts['active']" icon="truck" tone="blue" />
        <x-ui.stat label="Running late" :value="$counts['late']" icon="clock" :tone="$counts['late'] ? 'red' : 'forest'" hint="Past expected time, not yet delivered" />
        <x-ui.stat label="Delivered" :value="$counts['delivered']" icon="check-circle" />
        <x-ui.stat label="Failed / returned" :value="$counts['failed']" icon="x-circle" :tone="$counts['failed'] ? 'red' : 'zinc'" />
    </div>

    <x-ui.panel>
        <div class="flex flex-wrap items-end gap-3 border-b border-zinc-200 p-4">
            <div class="w-full sm:w-64"><flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Delivery, order or destination…" clearable /></div>
            <div class="w-full sm:w-48">
                <flux:select wire:model.live="status" aria-label="Status">
                    <flux:select.option value="">Status: All</flux:select.option>
                    <flux:select.option value="active">All active</flux:select.option>
                    @foreach ($statuses as $s)<flux:select.option value="{{ $s->value }}">{{ $s->label() }}</flux:select.option>@endforeach
                </flux:select>
            </div>
        </div>

        @if ($rows->isEmpty())
            <x-ui.empty icon="map-pin" title="No deliveries found" text="Deliveries are created when orders are added to a dispatch." />
        @else
            <flux:table :paginate="$rows" class="px-4" pagination:class="px-4 pb-3">
                <flux:table.columns>
                    <flux:table.column>Delivery</flux:table.column>
                    <flux:table.column>Order</flux:table.column>
                    <flux:table.column>Customer</flux:table.column>
                    <flux:table.column>Destination</flux:table.column>
                    <flux:table.column>Vehicle / driver</flux:table.column>
                    <flux:table.column>Expected</flux:table.column>
                    <flux:table.column>Delivered</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($rows as $row)
                        <flux:table.row :key="$row->id">
                            <flux:table.cell><a href="{{ route('deliveries.show', $row) }}" wire:navigate class="font-medium text-forest-700 hover:underline">{{ $row->reference }}</a></flux:table.cell>
                            <flux:table.cell>{{ $row->order->reference }}</flux:table.cell>
                            <flux:table.cell>{{ $row->order->customer->name }}</flux:table.cell>
                            <flux:table.cell class="max-w-56 truncate" title="{{ $row->destination }}">{{ $row->destination ?: '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $row->vehicle?->registration ?? '—' }}<span class="text-xs text-zinc-500">{{ $row->driver_name ? ' · '.$row->driver_name : '' }}</span></flux:table.cell>
                            <flux:table.cell>
                                {{ $row->expected_at?->format('d M H:i') ?? '—' }}
                                @if ($row->isLate())<flux:badge size="sm" color="red" inset="top bottom" class="ml-1">Late</flux:badge>@endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $row->delivered_at?->format('d M H:i') ?? '—' }}</flux:table.cell>
                            <flux:table.cell><x-ui.status :value="$row->status" /></flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-ui.panel>
</div>
