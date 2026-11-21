@php use App\Support\Options; @endphp
<div class="space-y-6">
    <x-ui.header image="site/delivery" title="Dispatch" description="Group ready orders onto a vehicle, record departure and transport cost, and follow each load to completion.">
        <flux:badge color="{{ $readyCount ? 'amber' : 'zinc' }}">{{ $readyCount }} order(s) ready for dispatch</flux:badge>
        @can('logistics.manage')
            <flux:button variant="primary" icon="plus" wire:click="create">New dispatch</flux:button>
        @endcan
    </x-ui.header>

    <x-ui.panel>
        <div class="flex flex-wrap items-end gap-3 border-b border-zinc-200 p-4">
            <div class="w-full sm:w-64"><flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Reference, destination or driver…" clearable /></div>
            <div class="w-full sm:w-48">
                <flux:select wire:model.live="status" aria-label="Status">
                    <flux:select.option value="">Status: All</flux:select.option>
                    @foreach ($statuses as $value => $label)<flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>@endforeach
                </flux:select>
            </div>
        </div>

        @if ($rows->isEmpty())
            <x-ui.empty icon="paper-airplane" title="No dispatches found" text="Create a dispatch once orders are marked ready for dispatch." />
        @else
            <flux:table :paginate="$rows" class="px-4" pagination:class="px-4 pb-3">
                <flux:table.columns>
                    <flux:table.column>Dispatch</flux:table.column>
                    <flux:table.column>From → To</flux:table.column>
                    <flux:table.column>Vehicle / driver</flux:table.column>
                    <flux:table.column>Orders</flux:table.column>
                    <flux:table.column>Departure</flux:table.column>
                    <flux:table.column>Expected arrival</flux:table.column>
                    <flux:table.column align="end">Transport cost</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($rows as $row)
                        <flux:table.row :key="$row->id">
                            <flux:table.cell class="font-medium text-zinc-900">{{ $row->reference }}</flux:table.cell>
                            <flux:table.cell>{{ $row->centre->name }} → {{ $row->destination }}</flux:table.cell>
                            <flux:table.cell>
                                {{ $row->vehicle?->registration ?? 'No vehicle' }}
                                <div class="text-xs text-zinc-500">{{ $row->driver_name }}{{ $row->transporter ? ' · '.$row->transporter->name : '' }}</div>
                            </flux:table.cell>
                            <flux:table.cell>
                                @foreach ($row->deliveries as $delivery)
                                    <a href="{{ route('deliveries.show', $delivery) }}" wire:navigate class="mr-1 text-forest-700 hover:underline">{{ $delivery->order->reference }}</a>
                                @endforeach
                            </flux:table.cell>
                            <flux:table.cell>{{ $row->departure_at?->format('d M H:i') ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $row->expected_arrival_at?->format('d M H:i') ?? '—' }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ Options::money($row->transport_cost) }}</flux:table.cell>
                            <flux:table.cell><x-ui.status :value="$row->status" list="DISPATCH_STATUS" /></flux:table.cell>
                            <flux:table.cell align="end">
                                @can('logistics.manage')
                                    @if ($row->status === 'scheduled')
                                        <div class="flex justify-end gap-1">
                                            <flux:button size="sm" variant="primary" wire:click="depart({{ $row->id }})">Depart</flux:button>
                                            <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="cancel({{ $row->id }})" wire:confirm="Cancel this dispatch?" aria-label="Cancel" />
                                        </div>
                                    @elseif ($row->status === 'dispatched')
                                        <flux:button size="sm" wire:click="inTransit({{ $row->id }})">Mark in transit</flux:button>
                                    @endif
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-ui.panel>

    <flux:modal wire:model.self="showForm" class="w-full max-w-3xl">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">New dispatch</flux:heading>
                <flux:subheading>Select the orders travelling on this load and assign transport.</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Orders ready for dispatch *</flux:label>
                @if ($readyOrders->isEmpty())
                    <flux:callout variant="secondary" icon="information-circle" heading="No orders are ready for dispatch. Mark processed orders as ready first." />
                @else
                    <div class="max-h-48 space-y-2 overflow-y-auto rounded-lg border border-zinc-200 p-3">
                        @foreach ($readyOrders as $order)
                            <flux:checkbox wire:model="orderIds" value="{{ $order->id }}"
                                :label="$order->reference.' — '.$order->customer->name.' · '.($order->delivery_address ?: 'no address').' · due '.($order->expected_delivery_date?->format('d M') ?? 'n/a')" />
                        @endforeach
                    </div>
                @endif
                <flux:error name="orderIds" />
            </flux:field>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="form.collection_centre_id" label="Dispatch from *">
                    <flux:select.option value="">— Select location —</flux:select.option>
                    @foreach ($centres as $id => $name)<flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>@endforeach
                </flux:select>
                <flux:select wire:model.live="form.delivery_route_id" label="Route">
                    <flux:select.option value="">— None —</flux:select.option>
                    @foreach ($routes as $id => $name)<flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>@endforeach
                </flux:select>
                <flux:input wire:model="form.destination" label="Destination *" />
                <flux:select wire:model.live="form.vehicle_id" label="Vehicle" description="Only available vehicles are listed. Required before departure.">
                    <flux:select.option value="">— Assign later —</flux:select.option>
                    @foreach ($vehicles as $vehicle)<flux:select.option value="{{ $vehicle->id }}">{{ $vehicle->registration }}{{ $vehicle->capacity_kg ? ' · '.Options::qty($vehicle->capacity_kg).' kg' : '' }}</flux:select.option>@endforeach
                </flux:select>
                <flux:select wire:model="form.transporter_id" label="Transporter">
                    <flux:select.option value="">— None —</flux:select.option>
                    @foreach ($transporters as $id => $name)<flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>@endforeach
                </flux:select>
                <flux:input wire:model="form.driver_name" label="Driver" />
                <flux:input wire:model="form.driver_phone" label="Driver phone" />
                <flux:input type="number" step="0.01" min="0" wire:model="form.transport_cost" label="Transport cost (KES)" description="Recorded as a transport expense when the load departs." />
                <flux:input type="datetime-local" wire:model="form.departure_at" label="Departure time *" />
                <flux:input type="datetime-local" wire:model="form.expected_arrival_at" label="Expected arrival *" />
            </div>
            <flux:input wire:model="form.notes" label="Notes" />

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">Schedule dispatch</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
