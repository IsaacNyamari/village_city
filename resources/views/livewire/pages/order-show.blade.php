@php
    use App\Support\Options;
    use App\Enums\OrderStatus as S;
    $o = $order;
@endphp
<div class="space-y-6">
    <x-ui.header :title="'Order '.$o->reference" :back="route('orders.index')"
        :description="$o->customer->name.' · placed '.$o->created_at->format('d M Y H:i').($o->source === 'marketplace' ? ' via marketplace' : '')">
        <x-ui.status :value="$o->status" />
        @can('orders.manage')
            @if ($o->status === S::PendingConfirmation)
                <flux:button variant="primary" icon="check" wire:click="confirm" wire:loading.attr="disabled">Confirm order</flux:button>
            @elseif ($o->status === S::AwaitingProcurement)
                <flux:button variant="primary" icon="arrow-path" wire:click="allocate">Re-check stock</flux:button>
                <flux:button icon="scissors" wire:click="proceedPartial" wire:confirm="Reduce the order to the quantities already allocated and continue? The invoice will be adjusted.">Proceed with partial</flux:button>
            @elseif ($o->status === S::Processing)
                <flux:button variant="primary" icon="cube" wire:click="markReady">Mark ready for dispatch</flux:button>
            @elseif ($o->status === S::ReadyForDispatch)
                @can('logistics.manage')
                    <flux:button variant="primary" icon="paper-airplane" :href="route('dispatches.index', ['new' => 1])" wire:navigate>Create dispatch</flux:button>
                @endcan
            @endif
        @endcan
        @can('marketplace.view')
            <flux:button icon="arrow-path" wire:click="reorder">Reorder</flux:button>
        @endcan
        @if ($canCancel)
            <flux:button variant="danger" icon="x-mark" wire:click="cancel" wire:confirm="Cancel this order? Any allocated stock will be returned.">Cancel order</flux:button>
        @endif
    </x-ui.header>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" :heading="session('status')" />
    @endif

    @if ($o->status === S::Cancelled)
        <flux:callout variant="danger" icon="x-circle" heading="This order was cancelled." />
    @else
        <x-ui.panel padded>
            <ol class="flex flex-wrap items-center gap-y-3">
                @foreach ($steps as $index => $step)
                    <li class="flex items-center">
                        <span @class(['flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                            'bg-forest-700 text-white' => $index <= $position, 'bg-zinc-100 text-zinc-500' => $index > $position])>
                            @if ($index < $position || $o->status === S::Delivered)<flux:icon.check variant="micro" />@else{{ $index + 1 }}@endif
                        </span>
                        <span @class(['ml-2 text-sm', 'font-semibold text-zinc-900' => $index === $position, 'text-zinc-500' => $index !== $position])>{{ $step->label() }}</span>
                        @unless ($loop->last)<span class="mx-3 h-px w-6 bg-zinc-300 sm:w-10"></span>@endunless
                    </li>
                @endforeach
            </ol>
            @if ($o->status === S::AwaitingProcurement)
                <p class="mt-3 text-sm text-gold-600">Some items are being sourced from farmers. The order continues automatically once stock arrives.</p>
            @endif
        </x-ui.panel>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.panel title="Items">
                <flux:table class="px-4">
                    <flux:table.columns>
                        <flux:table.column>Product</flux:table.column>
                        <flux:table.column align="end">Ordered</flux:table.column>
                        <flux:table.column align="end">Allocated</flux:table.column>
                        @if ($staff && in_array($o->status, [S::PendingConfirmation, S::Confirmed, S::AwaitingProcurement]))<flux:table.column align="end">In stock now</flux:table.column>@endif
                        <flux:table.column align="end">Unit price</flux:table.column>
                        <flux:table.column align="end">Discount</flux:table.column>
                        <flux:table.column align="end">Total</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($o->items as $item)
                            <flux:table.row :key="$item->id">
                                <flux:table.cell class="font-medium text-zinc-900">{{ $item->product->name }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ Options::qty($item->quantity) }} {{ $item->product->unit }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums {{ $item->shortfall() > 0 && $o->confirmed_at ? 'text-gold-600' : '' }}">{{ Options::qty($item->fulfilled_quantity) }}</flux:table.cell>
                                @if ($staff && in_array($o->status, [S::PendingConfirmation, S::Confirmed, S::AwaitingProcurement]))
                                    <flux:table.cell align="end" class="tabular-nums">{{ Options::qty($stock[$item->product_id] ?? 0) }}</flux:table.cell>
                                @endif
                                <flux:table.cell align="end" class="tabular-nums">{{ Options::money($item->unit_price) }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ (float) $item->discount > 0 ? Options::money($item->discount) : '—' }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums font-medium">{{ Options::money($item->total) }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
                <dl class="ml-auto w-full max-w-xs space-y-1 px-5 py-4 text-sm">
                    <div class="flex justify-between"><dt class="text-zinc-500">Subtotal</dt><dd class="tabular-nums">{{ Options::money($o->subtotal) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">Discount</dt><dd class="tabular-nums">− {{ Options::money($o->discount) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">Delivery charge</dt><dd class="tabular-nums">{{ Options::money($o->delivery_fee) }}</dd></div>
                    <div class="flex justify-between border-t border-zinc-200 pt-1 text-base font-semibold"><dt>Grand total</dt><dd class="tabular-nums">{{ Options::money($o->total) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">Paid</dt><dd class="tabular-nums">{{ Options::money($o->amount_paid) }}</dd></div>
                    <div class="flex justify-between font-medium"><dt>Balance</dt><dd class="tabular-nums">{{ Options::money($o->status === S::Cancelled ? 0 : $o->balance()) }}</dd></div>
                </dl>
            </x-ui.panel>

            <x-ui.panel title="Delivery tracking">
                @forelse ($o->deliveries as $delivery)
                    <div class="border-b border-zinc-100 px-5 py-4 last:border-0">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                @if ($staff && auth()->user()->can('logistics.view'))
                                    <a href="{{ route('deliveries.show', $delivery) }}" wire:navigate class="font-medium text-forest-700 hover:underline">{{ $delivery->reference }}</a>
                                @else
                                    <span class="font-medium">{{ $delivery->reference }}</span>
                                @endif
                                <p class="text-xs text-zinc-500">{{ $delivery->vehicle?->registration ? 'Vehicle '.$delivery->vehicle->registration.' · ' : '' }}{{ $delivery->driver_name ? 'Driver '.$delivery->driver_name : '' }}</p>
                            </div>
                            <x-ui.status :value="$delivery->status" />
                        </div>
                        <ol class="mt-3 space-y-2 border-l-2 border-forest-100 pl-4">
                            @foreach ($delivery->events as $event)
                                <li class="relative text-sm">
                                    <span class="absolute -left-[21px] top-1.5 size-2 rounded-full bg-forest-600"></span>
                                    <span class="font-medium">{{ ucwords(str_replace('_', ' ', $event->status)) }}</span>
                                    <span class="text-zinc-500">· {{ $event->created_at->format('d M Y H:i') }}</span>
                                    @if ($event->note)<span class="block text-zinc-600">{{ $event->note }}</span>@endif
                                </li>
                            @endforeach
                        </ol>
                        @if ($delivery->expected_at && ! $delivery->delivered_at)<p class="mt-2 text-xs text-zinc-500">Expected {{ $delivery->expected_at->format('d M Y H:i') }}</p>@endif
                    </div>
                @empty
                    <x-ui.empty icon="truck" title="Not dispatched yet" text="Delivery progress appears here once the order is assigned to a dispatch." />
                @endforelse
            </x-ui.panel>
        </div>

        <div class="space-y-6">
            <x-ui.panel title="Delivery details" padded>
                <x-ui.dl cols="grid-cols-1" :items="[
                    'Customer' => $o->customer->name,
                    'Contact' => trim($o->customer->contact_person.' '.$o->customer->phone),
                    'Delivery address' => $o->delivery_address,
                    'Expected delivery' => $o->expected_delivery_date?->format('d M Y'),
                    'Delivered' => $o->delivered_at?->format('d M Y H:i'),
                    'Special instructions' => $o->instructions,
                ]" />
            </x-ui.panel>

            <x-ui.panel title="Invoice & payments" padded>
                @if ($invoice)
                    <div class="flex items-center justify-between">
                        <div>
                            @can('invoices.view')
                                <a href="{{ route('invoices.show', $invoice) }}" wire:navigate class="font-medium text-forest-700 hover:underline">{{ $invoice->number }}</a>
                            @else
                                <span class="font-medium">{{ $invoice->number }}</span>
                            @endcan
                            <p class="text-xs text-zinc-500">Due {{ $invoice->due_date?->format('d M Y') }}</p>
                        </div>
                        <x-ui.status :value="$invoice->status" list="PAYMENT_STATUS" />
                    </div>
                    <ul class="mt-3 space-y-1.5 text-sm">
                        @forelse ($o->payments->where('status', 'completed') as $payment)
                            <li class="flex justify-between"><span class="text-zinc-600">{{ $payment->paid_at->format('d M Y') }} · {{ Options::label('PAYMENT_METHODS', $payment->method) }}</span><span class="tabular-nums">{{ Options::money($payment->amount) }}</span></li>
                        @empty
                            <li class="text-zinc-500">No payments received yet.</li>
                        @endforelse
                    </ul>
                @else
                    <p class="text-sm text-zinc-500">An invoice is issued when the order is confirmed.</p>
                @endif
            </x-ui.panel>

            @if ($staff && $o->procurements->isNotEmpty())
                <x-ui.panel title="Linked procurement" padded>
                    <ul class="space-y-2 text-sm">
                        @foreach ($o->procurements as $procurement)
                            <li class="flex items-center justify-between gap-2">
                                @can('procurement.view')
                                    <a href="{{ route('procurement.show', $procurement) }}" wire:navigate class="font-medium text-forest-700 hover:underline">{{ $procurement->reference }}</a>
                                @else
                                    <span>{{ $procurement->reference }}</span>
                                @endcan
                                <x-ui.status :value="$procurement->status" />
                            </li>
                        @endforeach
                    </ul>
                </x-ui.panel>
            @endif
        </div>
    </div>
</div>
