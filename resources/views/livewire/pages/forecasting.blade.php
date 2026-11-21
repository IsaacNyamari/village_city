@php use App\Support\Options; @endphp
<div class="space-y-6">
    <x-ui.header title="Demand Forecasting" description="Rule-based estimates from your own order history." />

    <flux:callout variant="warning" icon="information-circle" heading="System-generated estimates, not verified data">
        <flux:callout.text>Forecasts use a {{ $weeks }}-week moving average adjusted for the recent trend (and same-month seasonality when a year of history exists). Treat them as a starting point for planning; confidence is low until there are several weeks of orders. Weights and the history window can be changed in Settings.</flux:callout.text>
    </flux:callout>

    <x-ui.panel title="Procurement recommendations" description="Suggested quantities to cover next week's forecast demand plus safety stock, net of stock on hand and open procurement.">
        @if ($recommendations->isEmpty())
            <x-ui.empty icon="check-circle" :title="$hasHistory ? 'No additional procurement suggested' : 'Not enough order history yet'" :text="$hasHistory ? 'Current stock and open procurement cover the forecast.' : 'Recommendations appear once orders have been recorded.'" />
        @else
            <flux:table class="px-4">
                <flux:table.columns><flux:table.column>Priority</flux:table.column><flux:table.column>Commodity</flux:table.column><flux:table.column align="end">On hand</flux:table.column><flux:table.column align="end">Incoming</flux:table.column><flux:table.column align="end">Forecast / week</flux:table.column><flux:table.column align="end">Suggested buy</flux:table.column><flux:table.column align="end">Est. cost</flux:table.column><flux:table.column>Why</flux:table.column></flux:table.columns>
                <flux:table.rows>
                    @foreach ($recommendations as $r)
                        <flux:table.row :key="$r->product->id">
                            <flux:table.cell><flux:badge size="sm" :color="['Urgent' => 'red', 'High' => 'amber', 'Normal' => 'zinc'][$r->priority]" inset="top bottom">{{ $r->priority }}</flux:badge></flux:table.cell>
                            <flux:table.cell class="font-medium text-zinc-900">{{ $r->product->name }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ Options::qty($r->on_hand) }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ Options::qty($r->incoming) }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ Options::qty($r->forecast) }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums font-semibold">{{ Options::qty($r->suggested) }} {{ $r->product->unit }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ Options::money($r->estimated_cost) }}</flux:table.cell>
                            <flux:table.cell class="whitespace-normal text-xs text-zinc-600">{{ implode('; ', $r->reasons) }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-ui.panel>

    <x-ui.panel title="Weekly demand forecast">
        @if ($forecast->isEmpty())
            <x-ui.empty icon="arrow-trending-up" title="No forecast available" text="Record orders to build demand history." />
        @else
            <flux:table class="px-4">
                <flux:table.columns><flux:table.column>Commodity</flux:table.column><flux:table.column align="end">Avg / week</flux:table.column><flux:table.column align="end">Trend</flux:table.column><flux:table.column align="end">Forecast next week</flux:table.column><flux:table.column align="end">Stock cover</flux:table.column><flux:table.column>Confidence</flux:table.column></flux:table.columns>
                <flux:table.rows>
                    @foreach ($forecast as $r)
                        <flux:table.row :key="$r->product->id">
                            <flux:table.cell class="font-medium text-zinc-900">{{ $r->product->name }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ Options::qty($r->average) }} {{ $r->product->unit }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums {{ $r->trend > 0 ? 'text-forest-700' : ($r->trend < 0 ? 'text-red-600' : '') }}">{{ $r->trend > 0 ? '+' : '' }}{{ $r->trend }}%</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums font-medium">{{ Options::qty($r->forecast) }} {{ $r->product->unit }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ $r->days_cover !== null ? $r->days_cover.' days' : '—' }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" :color="['High' => 'green', 'Medium' => 'amber', 'Low' => 'zinc'][$r->confidence]" inset="top bottom">{{ $r->confidence }}</flux:badge></flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-ui.panel>
</div>
