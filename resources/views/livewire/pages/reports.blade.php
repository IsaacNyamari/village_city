@php use App\Support\Options; @endphp
<div class="space-y-6">
    <x-ui.header title="Reports" description="Filter, review and export operational and financial reports.">
        <flux:button icon="arrow-down-tray" wire:click="export" wire:loading.attr="disabled" wire:target="export">Export CSV</flux:button>
    </x-ui.header>

    <x-ui.panel>
        <div class="grid gap-3 border-b border-zinc-200 p-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:select wire:model.live="report" label="Report">@foreach ($reports as $key => $label)<flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>@endforeach</flux:select>
            @if (in_array('from', $used))<flux:input type="date" wire:model.live="filters.from" label="From" />@endif
            @if (in_array('to', $used))<flux:input type="date" wire:model.live="filters.to" label="To" />@endif
            @if (in_array('county', $used))<flux:select wire:model.live="filters.county" label="County"><flux:select.option value="">All counties</flux:select.option>@foreach ($counties as $id => $n)<flux:select.option value="{{ $id }}">{{ $n }}</flux:select.option>@endforeach</flux:select>@endif
            @if (in_array('centre', $used))<flux:select wire:model.live="filters.centre" label="Collection centre"><flux:select.option value="">All centres</flux:select.option>@foreach ($centres as $id => $n)<flux:select.option value="{{ $id }}">{{ $n }}</flux:select.option>@endforeach</flux:select>@endif
            @if (in_array('product', $used))<flux:select wire:model.live="filters.product" label="Product"><flux:select.option value="">All products</flux:select.option>@foreach ($products as $id => $n)<flux:select.option value="{{ $id }}">{{ $n }}</flux:select.option>@endforeach</flux:select>@endif
            @if (in_array('supplier', $used))<flux:select wire:model.live="filters.supplier" label="Supplier / farmer"><flux:select.option value="">All</flux:select.option>@foreach ($sources as $id => $n)<flux:select.option value="{{ $id }}">{{ $n }}</flux:select.option>@endforeach</flux:select>@endif
            @if (in_array('customer', $used))<flux:select wire:model.live="filters.customer" label="Customer"><flux:select.option value="">All customers</flux:select.option>@foreach ($customers as $id => $n)<flux:select.option value="{{ $id }}">{{ $n }}</flux:select.option>@endforeach</flux:select>@endif
            @if (in_array('status', $used))
                <flux:input wire:model.live.debounce.400ms="filters.status" label="Status" placeholder="e.g. active, delivered, inspected" />
            @endif
        </div>

        @if ($data['totals'])
            <div class="flex flex-wrap gap-x-8 gap-y-1 border-b border-zinc-200 bg-forest-50 px-5 py-3 text-sm">
                @foreach ($data['totals'] as $label => $value)
                    <span><span class="text-forest-800">{{ $label }}:</span> <strong class="tabular-nums">{{ is_numeric($value) && str_contains(strtolower($label), 'value') || str_contains(strtolower($label), 'liab') || str_contains(strtolower($label), 'outstanding') || str_contains(strtolower($label), 'revenue') || str_contains(strtolower($label), 'profit') || str_contains(strtolower($label), 'sales') || str_contains(strtolower($label), 'payable') || str_contains(strtolower($label), 'lost') ? Options::money($value) : (is_numeric($value) ? Options::qty($value) : $value) }}</strong></span>
                @endforeach
            </div>
        @endif

        @if (count($data['rows']) === 0)
            <x-ui.empty icon="chart-bar" title="No data for these filters" text="Widen the date range or clear a filter." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full whitespace-nowrap text-sm">
                    <thead><tr class="border-b border-zinc-200 bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500">@foreach ($data['columns'] as $col)<th class="px-4 py-2.5 font-medium">{{ $col }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach (array_slice($data['rows'], 0, 500) as $row)
                            <tr class="border-b border-zinc-100 hover:bg-zinc-50">@foreach ($row as $cell)<td class="px-4 py-2 {{ is_numeric($cell) ? 'text-right tabular-nums' : '' }}">{{ $cell === null || $cell === '' ? '—' : (is_float($cell) ? Options::qty($cell) : $cell) }}</td>@endforeach</tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="border-t border-zinc-200 px-5 py-2.5 text-xs text-zinc-500">{{ number_format(count($data['rows'])) }} row(s){{ count($data['rows']) > 500 ? ' — showing the first 500; the CSV export contains all rows' : '' }}.</p>
        @endif
    </x-ui.panel>
</div>
