@php use App\Support\Options; @endphp
<div class="space-y-6">
    <x-ui.header title="Invoices" description="Invoices are issued automatically when an order is confirmed and settle as payments are recorded." />

    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.stat label="Outstanding receivables" :value="Options::money($outstanding)" icon="document-text" tone="gold" />
        <x-ui.stat label="Overdue" :value="Options::money($overdueTotal)" icon="exclamation-circle" :tone="$overdueTotal > 0 ? 'red' : 'forest'" hint="Past due date and unpaid" />
    </div>

    <x-ui.panel>
        <div class="flex flex-wrap items-end gap-3 border-b border-zinc-200 p-4">
            <div class="w-full sm:w-64"><flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Invoice or customer…" clearable /></div>
            <div class="w-full sm:w-48">
                <flux:select wire:model.live="status" aria-label="Status">
                    <flux:select.option value="">Status: All</flux:select.option>
                    @foreach ($statuses as $value => $label)<flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>@endforeach
                </flux:select>
            </div>
            <flux:checkbox wire:model.live="overdue" label="Overdue only" />
        </div>
        @if ($rows->isEmpty())
            <x-ui.empty icon="document-text" title="No invoices found" text="Confirm an order to generate its invoice." />
        @else
            <flux:table :paginate="$rows" class="px-4" pagination:class="px-4 pb-3">
                <flux:table.columns>
                    <flux:table.column>Invoice</flux:table.column><flux:table.column>Customer</flux:table.column><flux:table.column>Order</flux:table.column>
                    <flux:table.column>Issued</flux:table.column><flux:table.column>Due</flux:table.column>
                    <flux:table.column align="end">Total</flux:table.column><flux:table.column align="end">Balance</flux:table.column><flux:table.column>Status</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($rows as $row)
                        <flux:table.row :key="$row->id">
                            <flux:table.cell><a href="{{ route('invoices.show', $row) }}" wire:navigate class="font-medium text-forest-700 hover:underline">{{ $row->number }}</a></flux:table.cell>
                            <flux:table.cell>{{ $row->customer->name }}</flux:table.cell>
                            <flux:table.cell>{{ $row->order->reference }}</flux:table.cell>
                            <flux:table.cell>{{ $row->issue_date->format('d M Y') }}</flux:table.cell>
                            <flux:table.cell>{{ $row->due_date?->format('d M Y') }} @if ($row->isOverdue())<flux:badge size="sm" color="red" inset="top bottom">Overdue</flux:badge>@endif</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ Options::money($row->total) }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ Options::money($row->status === 'void' ? 0 : $row->balance()) }}</flux:table.cell>
                            <flux:table.cell><x-ui.status :value="$row->status" list="PAYMENT_STATUS" /></flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-ui.panel>
</div>
