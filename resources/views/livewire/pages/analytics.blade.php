@php use App\Support\Options; @endphp
<div class="space-y-6">
    <x-ui.header title="Analytics" description="Business performance computed from recorded orders, procurement and expenses.">
        <flux:select wire:model.live="months" class="w-44" aria-label="Period"><flux:select.option value="3">Last 3 months</flux:select.option><flux:select.option value="6">Last 6 months</flux:select.option><flux:select.option value="12">Last 12 months</flux:select.option></flux:select>
    </x-ui.header>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <x-ui.stat label="Revenue" :value="Options::money($revenue)" icon="banknotes" />
        <x-ui.stat label="Procurement" :value="Options::money($procurement)" icon="shopping-cart" tone="blue" />
        <x-ui.stat label="Gross profit" :value="Options::money($gross)" icon="chart-bar" tone="gold" :hint="$margin !== null ? $margin.'% of goods revenue' : 'No sales yet'" />
        <x-ui.stat label="Operating expenses" :value="Options::money($expenses)" icon="receipt-percent" tone="red" />
        <x-ui.stat label="Gross profit − expenses" :value="Options::money($gross - $expenses)" icon="scale" tone="zinc" hint="Operating result, excludes tax & other items" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.panel title="Sales vs procurement by month" padded>
            <x-ui.chart type="bar" :labels="$sales['labels']" :datasets="[['label' => 'Sales', 'data' => $sales['values']], ['label' => 'Procurement', 'data' => $spend['values']]]" />
        </x-ui.panel>
        <x-ui.panel title="Top-selling commodities" description="Last 30 days, by value" padded>
            <x-ui.chart type="bar" :horizontal="true" :labels="$insights['top_selling']->pluck('name')" :datasets="[['label' => 'Revenue (KES)', 'data' => $insights['top_selling']->pluck('revenue')->all()]]" />
        </x-ui.panel>
        <x-ui.panel title="Most profitable commodities" description="Gross profit on stock sold, last 30 days" padded>
            <x-ui.chart type="bar" :horizontal="true" :labels="$insights['profitable']->pluck('name')" :datasets="[['label' => 'Gross profit (KES)', 'data' => $insights['profitable']->pluck('profit')->all()]]" />
        </x-ui.panel>
        <x-ui.panel title="Wastage by commodity" description="Damaged, expired and wasted stock at cost, last 30 days" padded>
            <x-ui.chart type="bar" :horizontal="true" :labels="$insights['wastage']->pluck('name')" :datasets="[['label' => 'Wastage value (KES)', 'data' => $insights['wastage']->pluck('value')->all()]]" />
        </x-ui.panel>
    </div>
    <p class="text-xs text-zinc-500">For demand forecasts, procurement recommendations and supplier matching see <a class="underline" href="{{ route('forecasting') }}" wire:navigate>Demand Forecasting</a> and <a class="underline" href="{{ route('market') }}" wire:navigate>Market Intelligence</a>.</p>
</div>
