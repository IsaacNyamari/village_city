@php use App\Support\Options; @endphp
<div class="space-y-6">
    <x-ui.header title="Financial Transactions" description="The append-only cash ledger. Every customer payment, supplier payment and expense is recorded here; mistakes appear as reversing entries." />

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Money in" :value="Options::money($in)" icon="arrow-down-left" />
        <x-ui.stat label="Money out" :value="Options::money($out)" icon="arrow-up-right" tone="red" />
        <x-ui.stat label="Net cash movement" :value="Options::money($in - $out)" icon="scale" tone="gold" hint="For the filtered period" />
    </div>

    <x-ui.panel>
        <div class="flex flex-wrap items-end gap-3 border-b border-zinc-200 p-4">
            <div class="w-full sm:w-40"><flux:select wire:model.live="direction" aria-label="Direction"><flux:select.option value="">Direction: All</flux:select.option><flux:select.option value="in">Money in</flux:select.option><flux:select.option value="out">Money out</flux:select.option></flux:select></div>
            <div class="w-full sm:w-60"><flux:select wire:model.live="category" aria-label="Category"><flux:select.option value="">Category: All</flux:select.option>@foreach ($categories as $v => $l)<flux:select.option value="{{ $v }}">{{ $l }}</flux:select.option>@endforeach</flux:select></div>
            <div class="w-full sm:w-40"><flux:input type="date" wire:model.live="from" label="From" /></div>
            <div class="w-full sm:w-40"><flux:input type="date" wire:model.live="to" label="To" /></div>
        </div>
        @if ($rows->isEmpty())
            <x-ui.empty icon="arrows-right-left" title="No transactions in this period" />
        @else
            <flux:table :paginate="$rows" class="px-4" pagination:class="px-4 pb-3">
                <flux:table.columns><flux:table.column>Date</flux:table.column><flux:table.column>Category</flux:table.column><flux:table.column>Description</flux:table.column><flux:table.column>Reference</flux:table.column><flux:table.column>Method</flux:table.column><flux:table.column align="end">Amount</flux:table.column><flux:table.column>By</flux:table.column></flux:table.columns>
                <flux:table.rows>
                    @foreach ($rows as $row)
                        <flux:table.row :key="$row->id">
                            <flux:table.cell>{{ $row->transaction_date->format('d M Y') }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" :color="$row->direction === 'in' ? 'green' : 'red'" inset="top bottom">{{ $categories[$row->category] ?? $row->category }}</flux:badge></flux:table.cell>
                            <flux:table.cell class="max-w-72 truncate" title="{{ $row->description }}">{{ $row->description }}</flux:table.cell>
                            <flux:table.cell>{{ $row->reference ?: '—' }}</flux:table.cell>
                            <flux:table.cell>{{ Options::label('PAYMENT_METHODS', $row->method) }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums font-medium {{ $row->direction === 'in' ? 'text-forest-700' : 'text-red-600' }}">{{ $row->direction === 'in' ? '+' : '−' }} {{ Options::money($row->amount) }}</flux:table.cell>
                            <flux:table.cell>{{ $row->user?->name ?? 'System' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-ui.panel>
</div>
