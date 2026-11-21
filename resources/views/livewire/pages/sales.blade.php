@php use App\Support\Options; @endphp
<div class="space-y-6">
    <x-ui.header title="Sales" description="Orders that have been confirmed and had stock allocated. Cancelled and unconfirmed orders are excluded." />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat label="Sales value" :value="Options::money($revenue)" icon="presentation-chart-line" />
        <x-ui.stat label="Orders" :value="$orders" icon="shopping-cart" tone="blue" :hint="$delivered.' delivered'" />
        <x-ui.stat label="Goods revenue" :value="Options::money($goods)" icon="banknotes" tone="zinc" hint="Excludes delivery charges" />
        <x-ui.stat label="Gross profit" :value="Options::money($profit)" icon="chart-bar" tone="gold" hint="Not net profit" />
    </div>

    <x-ui.panel>
        <div class="flex flex-wrap items-end gap-3 border-b border-zinc-200 p-4">
            <div class="w-full sm:w-40"><flux:input type="date" wire:model.live="from" label="From" /></div>
            <div class="w-full sm:w-40"><flux:input type="date" wire:model.live="to" label="To" /></div>
            <div class="w-full sm:w-56"><flux:select wire:model.live="customer" label="Customer"><flux:select.option value="">All customers</flux:select.option>@foreach ($customers as $id => $name)<flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>@endforeach</flux:select></div>
        </div>
        @if ($rows->isEmpty())
            <x-ui.empty icon="presentation-chart-line" title="No sales in this period" />
        @else
            <flux:table :paginate="$rows" class="px-4" pagination:class="px-4 pb-3">
                <flux:table.columns><flux:table.column>Order</flux:table.column><flux:table.column>Confirmed</flux:table.column><flux:table.column>Customer</flux:table.column><flux:table.column align="end">Total</flux:table.column><flux:table.column>Status</flux:table.column><flux:table.column>Payment</flux:table.column></flux:table.columns>
                <flux:table.rows>
                    @foreach ($rows as $row)
                        <flux:table.row :key="$row->id">
                            <flux:table.cell><a href="{{ route('orders.show', $row) }}" wire:navigate class="font-medium text-forest-700 hover:underline">{{ $row->reference }}</a></flux:table.cell>
                            <flux:table.cell>{{ $row->confirmed_at?->format('d M Y') }}</flux:table.cell>
                            <flux:table.cell>{{ $row->customer->name }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ Options::money($row->total) }}</flux:table.cell>
                            <flux:table.cell><x-ui.status :value="$row->status" /></flux:table.cell>
                            <flux:table.cell><x-ui.status :value="$row->payment_status" list="PAYMENT_STATUS" /></flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-ui.panel>
</div>
