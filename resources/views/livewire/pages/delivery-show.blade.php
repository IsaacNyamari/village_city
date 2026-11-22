@php
    use App\Support\Options;
    use App\Enums\DeliveryStatus as S;
    $d = $delivery;
@endphp
<div class="space-y-6">
    <x-ui.header :title="'Delivery '.$d->reference" :back="route('deliveries.index')" :description="'Order '.$d->order->reference.' for '.$d->order->customer->name">
        <x-ui.status :value="$d->status" />
        @if ($d->isLate())<flux:badge color="red">Late</flux:badge>@endif
        @can('logistics.manage')
            @foreach ($next as $status)
                @if (! in_array($status, [S::Assigned]))
                    <flux:button :variant="$status === S::Delivered ? 'primary' : (in_array($status, [S::Failed, S::Returned]) ? 'danger' : 'outline')" wire:click="open('{{ $status->value }}')">
                        Mark {{ strtolower($status->label()) }}
                    </flux:button>
                @endif
            @endforeach
        @endcan
    </x-ui.header>

    @if ($d->dispatch?->status === 'scheduled')
        <flux:callout variant="secondary" icon="information-circle" heading="This delivery is on a scheduled dispatch. Record the departure under Logistics → Dispatch to start tracking it." />
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.panel title="Delivery details" padded>
                <x-ui.dl :items="[
                    'Dispatch' => $d->dispatch?->reference,
                    'Dispatch location' => $d->origin,
                    'Destination' => $d->destination,
                    'Vehicle' => $d->vehicle?->registration,
                    'Driver' => $d->driver_name,
                    'Transporter' => $d->dispatch?->transporter?->name,
                    'Dispatch time' => $d->dispatched_at?->format('d M Y H:i'),
                    'Expected delivery' => $d->expected_at?->format('d M Y H:i'),
                    'Actual delivery' => $d->delivered_at?->format('d M Y H:i'),
                    'Recipient' => trim($d->recipient_name.' '.$d->recipient_phone),
                    'Delivery notes' => $d->notes,
                    'Parcel code' => $d->parcel_code,
                    'Last-mile' => $d->last_mile_provider ? (\App\Models\Delivery::LAST_MILE[$d->last_mile_provider] ?? $d->last_mile_provider).' · '.trim($d->rider_name.' '.$d->rider_phone) : null,
                ]" />
                @can('logistics.manage')
                    <form wire:submit="saveLastMile" class="mt-4 grid gap-3 border-t border-zinc-200 pt-4 sm:grid-cols-4">
                        <flux:select wire:model="lastMile.provider" label="Last-mile by">
                            <flux:select.option value="">— none —</flux:select.option>
                            @foreach (\App\Models\Delivery::LAST_MILE as $k => $v)<flux:select.option value="{{ $k }}">{{ $v }}</flux:select.option>@endforeach
                        </flux:select>
                        <flux:input wire:model="lastMile.rider_name" label="Rider / driver" />
                        <flux:input wire:model="lastMile.rider_phone" label="Rider phone" />
                        <div class="flex items-end"><flux:button type="submit" size="sm">Save hand-off</flux:button></div>
                    </form>
                @endcan
                @if ($d->pod_path)
                    <div class="mt-4">
                        <flux:button size="sm" icon="document-check" href="{{ Storage::disk('public')->url($d->pod_path) }}" target="_blank">View proof of delivery</flux:button>
                    </div>
                @endif
            </x-ui.panel>

            <x-ui.panel title="Goods on this delivery">
                <flux:table class="px-4">
                    <flux:table.columns>
                        <flux:table.column>Product</flux:table.column>
                        <flux:table.column align="end">Quantity</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($d->order->items as $item)
                            <flux:table.row :key="$item->id">
                                <flux:table.cell class="font-medium text-zinc-900">{{ $item->product->name }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ Options::qty($item->fulfilled_quantity) }} {{ $item->product->unit }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </x-ui.panel>
        </div>

        <x-ui.panel title="Timeline" padded>
            <ol class="space-y-4 border-l-2 border-forest-100 pl-5">
                @foreach ($d->events as $event)
                    <li class="relative">
                        <span class="absolute -left-[27px] top-1 size-3 rounded-full border-2 border-white bg-forest-600 ring-1 ring-forest-200"></span>
                        <p class="text-sm font-semibold text-zinc-900">{{ ucwords(str_replace('_', ' ', $event->status)) }}</p>
                        <p class="text-xs text-zinc-500">{{ $event->created_at->format('d M Y H:i') }} · {{ $event->user?->name ?? 'System' }}</p>
                        @if ($event->note)<p class="mt-0.5 text-sm text-zinc-600">{{ $event->note }}</p>@endif
                    </li>
                @endforeach
            </ol>
        </x-ui.panel>
    </div>

    <flux:modal wire:model.self="showUpdate" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">Mark delivery as {{ $to ? strtolower(S::from($to)->label()) : '' }}</flux:heading>

            @if ($to === 'delivered')
                <flux:input wire:model="form.recipient_name" label="Received by *" />
                <flux:input wire:model="form.recipient_phone" label="Recipient phone" />
                <flux:field>
                    <flux:label>Proof of delivery (photo or PDF, optional)</flux:label>
                    <input type="file" wire:model="proof" accept="image/*,application/pdf" class="block w-full text-sm text-zinc-600 file:mr-2 file:rounded-md file:border-0 file:bg-forest-50 file:px-2 file:py-1.5 file:text-forest-800" />
                    <flux:error name="proof" />
                </flux:field>
            @endif

            <flux:textarea wire:model="form.notes" rows="2" :label="in_array($to, ['failed', 'returned']) ? 'Reason *' : 'Notes'" />

            @if (in_array($to, ['failed', 'returned']))
                <flux:text class="text-xs">The order will return to “Ready for dispatch” so it can be rescheduled.</flux:text>
            @endif

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showUpdate', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save,proof">Save</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
