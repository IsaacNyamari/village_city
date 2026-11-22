@php use App\Support\Options; @endphp
<div class="space-y-6">
    <x-ui.header image="site/market-crates" title="Order Aggregation" description="Customers and quantities per commodity, then per collection centre with what each customer has paid for.">
        <flux:input type="date" wire:model.live="date" aria-label="Date" />
        <flux:switch wire:model.live="paidOnly" label="Paid only" />
    </x-ui.header>

    <x-ui.panel>
        <div class="border-b border-zinc-200 p-4"><flux:heading>By commodity</flux:heading></div>
        @if ($byCommodity->isEmpty())
            <x-ui.empty icon="shopping-cart" title="No open orders for this date" text="Orders due on this date will be rolled up here." />
        @else
            <flux:table class="px-4">
                <flux:table.columns>
                    <flux:table.column>Commodity</flux:table.column>
                    <flux:table.column align="end">Customers</flux:table.column>
                    <flux:table.column align="end">Orders</flux:table.column>
                    <flux:table.column align="end">Total quantity</flux:table.column>
                    <flux:table.column align="end">Value</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($byCommodity as $row)
                        <flux:table.row :key="$row['product']->id">
                            <flux:table.cell class="font-medium text-zinc-900">{{ $row['product']->name }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ $row['customers'] }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ $row['orders'] }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ Options::qty($row['quantity']) }} {{ $row['product']->unit }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ Options::money($row['value']) }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-ui.panel>

    @foreach ($byCentre as $group)
        <x-ui.panel wire:key="centre-{{ $group['centre']?->id ?? 0 }}">
            <div class="flex items-center justify-between border-b border-zinc-200 p-4">
                <flux:heading>{{ $group['centre']?->name ?? 'Not yet assigned to a collection centre' }}</flux:heading>
                <flux:badge>{{ $group['rows']->count() }} customer(s)</flux:badge>
            </div>
            <flux:table class="px-4">
                <flux:table.columns>
                    <flux:table.column>Customer</flux:table.column>
                    <flux:table.column>Items</flux:table.column>
                    <flux:table.column>Payment</flux:table.column>
                    <flux:table.column>Centre</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($group['rows'] as $row)
                        <flux:table.row :key="$row['customer']->id">
                            <flux:table.cell class="font-medium text-zinc-900">{{ $row['customer']->name }}</flux:table.cell>
                            <flux:table.cell>
                                @foreach ($row['items'] as $item)
                                    <div class="text-sm">{{ $item->product->name }} · {{ Options::qty($item->quantity) }} {{ $item->product->unit }} <span class="text-xs text-zinc-500">({{ $item->order->reference }})</span></div>
                                @endforeach
                            </flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" color="{{ $row['paid'] ? 'green' : 'amber' }}">{{ $row['paid'] ? 'Paid · ready to ship' : 'Awaiting payment' }}</flux:badge></flux:table.cell>
                            <flux:table.cell>
                                @can('orders.manage')
                                    @foreach ($row['items'] as $item)
                                        <flux:select size="sm" wire:change="assign({{ $item->id }}, $event.target.value)" class="mb-1">
                                            <flux:select.option value="">— none —</flux:select.option>
                                            @foreach ($centres as $id => $name)<flux:select.option value="{{ $id }}" :selected="$item->collection_centre_id === $id">{{ $name }}</flux:select.option>@endforeach
                                        </flux:select>
                                    @endforeach
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-ui.panel>
    @endforeach
</div>
