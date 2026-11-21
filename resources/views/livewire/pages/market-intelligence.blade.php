@php use App\Support\Options; @endphp
<div class="space-y-6">
    <x-ui.header title="Market Intelligence" description="Operational insights and supplier matching derived from the last 30 days of records." />

    <flux:callout variant="warning" icon="information-circle" heading="System-generated insights, not verified data">
        <flux:callout.text>Rankings and observations are calculated with simple rules from your own transactions. No external AI service is connected; this module is structured so one can be added later.</flux:callout.text>
    </flux:callout>

    <x-ui.panel title="Supplier matching" description="Ranks farmers and suppliers for a commodity: quality 40 · reliability 25 · price 20 · availability 15." padded>
        <div class="max-w-sm"><flux:select wire:model.live="product" aria-label="Commodity"><flux:select.option value="">— Choose a commodity —</flux:select.option>@foreach ($products as $id => $name)<flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>@endforeach</flux:select></div>
        @if ($selected)
            <div class="mt-4">
                @if ($matches->isEmpty())
                    <x-ui.empty icon="user-group" title="No suppliers found for {{ $selected->name }}" text="Link commodities to farmers and suppliers, or record procurement, to build a ranking." />
                @else
                    <flux:table>
                        <flux:table.columns><flux:table.column>Score</flux:table.column><flux:table.column>Name</flux:table.column><flux:table.column>Type</flux:table.column><flux:table.column>County</flux:table.column><flux:table.column align="end">Quality</flux:table.column><flux:table.column align="end">Reliability</flux:table.column><flux:table.column align="end">Avg price</flux:table.column><flux:table.column align="end">Expected harvest (45d)</flux:table.column><flux:table.column align="end">Deals</flux:table.column></flux:table.columns>
                        <flux:table.rows>
                            @foreach ($matches as $m)
                                <flux:table.row :key="$m->type.$m->party->id">
                                    <flux:table.cell><flux:badge size="sm" :color="$m->score >= 75 ? 'green' : ($m->score >= 50 ? 'amber' : 'zinc')" inset="top bottom">{{ $m->score }}/100</flux:badge></flux:table.cell>
                                    <flux:table.cell class="font-medium text-zinc-900">{{ $m->name }}</flux:table.cell>
                                    <flux:table.cell>{{ $m->type }}</flux:table.cell>
                                    <flux:table.cell>{{ $m->county ?? '—' }}</flux:table.cell>
                                    <flux:table.cell align="end">{{ $m->quality !== null ? $m->quality.'%' : 'No history' }}</flux:table.cell>
                                    <flux:table.cell align="end">{{ $m->reliability !== null ? $m->reliability.'%' : 'No history' }}</flux:table.cell>
                                    <flux:table.cell align="end" class="tabular-nums">{{ $m->avg_price !== null ? Options::money($m->avg_price) : '—' }}</flux:table.cell>
                                    <flux:table.cell align="end">{{ $m->expected_harvest > 0 ? Options::qty($m->expected_harvest).' '.$selected->unit : '—' }}</flux:table.cell>
                                    <flux:table.cell align="end">{{ $m->transactions }}</flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </div>
        @endif
    </x-ui.panel>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.panel title="Top-selling commodities">
            @forelse ($insights['top_selling'] as $r)
                <div class="flex justify-between gap-3 border-b border-zinc-100 px-5 py-2.5 text-sm last:border-0"><span class="font-medium">{{ $r->name }}</span><span class="tabular-nums text-zinc-600">{{ Options::qty($r->quantity) }} {{ $r->unit }} · {{ Options::money($r->revenue) }}</span></div>
            @empty <x-ui.empty icon="chart-bar" title="No sales data yet" /> @endforelse
        </x-ui.panel>
        <x-ui.panel title="Most profitable commodities" description="Gross profit on stock sold">
            @forelse ($insights['profitable'] as $r)
                <div class="flex justify-between gap-3 border-b border-zinc-100 px-5 py-2.5 text-sm last:border-0"><span class="font-medium">{{ $r->name }}</span><span class="tabular-nums text-zinc-600">{{ Options::money($r->profit) }}</span></div>
            @empty <x-ui.empty icon="chart-bar" title="No fulfilled sales yet" /> @endforelse
        </x-ui.panel>
        <x-ui.panel title="Slow-moving stock" description="In stock with no orders in the last 30 days">
            @forelse ($insights['slow_moving'] as $r)
                <div class="flex justify-between gap-3 border-b border-zinc-100 px-5 py-2.5 text-sm last:border-0"><span class="font-medium">{{ $r->name }}</span><span class="tabular-nums text-zinc-600">{{ Options::qty($r->on_hand) }} {{ $r->unit }}</span></div>
            @empty <x-ui.empty icon="check-circle" title="Nothing identified" /> @endforelse
        </x-ui.panel>
        <x-ui.panel title="High-wastage commodities" description="Damaged, expired and wasted, at cost">
            @forelse ($insights['wastage'] as $r)
                <div class="flex justify-between gap-3 border-b border-zinc-100 px-5 py-2.5 text-sm last:border-0"><span class="font-medium">{{ $r->name }}</span><span class="tabular-nums text-zinc-600">{{ Options::qty($r->quantity) }} {{ $r->unit }} · {{ Options::money($r->value) }}</span></div>
            @empty <x-ui.empty icon="check-circle" title="No wastage recorded" /> @endforelse
        </x-ui.panel>
        <x-ui.panel title="Best-performing sourcing counties" description="By value of accepted produce">
            @forelse ($insights['counties'] as $r)
                <div class="flex justify-between gap-3 border-b border-zinc-100 px-5 py-2.5 text-sm last:border-0"><span class="font-medium">{{ $r->name }}</span><span class="tabular-nums text-zinc-600">{{ Options::money($r->value) }} · {{ $r->quality }}% accepted</span></div>
            @empty <x-ui.empty icon="globe-europe-africa" title="No inspected procurement yet" /> @endforelse
        </x-ui.panel>
        <x-ui.panel title="Delivery performance" padded>
            @php $d = $insights['deliveries']; @endphp
            @if ($d['total'] === 0)
                <x-ui.empty icon="truck" title="No deliveries in the last 30 days" />
            @else
                <x-ui.dl cols="grid-cols-2" :items="['Deliveries' => $d['total'], 'Completed' => $d['completed'], 'Late (incl. in progress)' => $d['late'], 'On-time rate' => $d['on_time_rate'] !== null ? $d['on_time_rate'].'%' : 'n/a']" />
            @endif
        </x-ui.panel>
        <x-ui.panel title="Outstanding payment risks" description="Customers with overdue invoices" class="lg:col-span-2">
            @forelse ($insights['payment_risks'] as $r)
                <div class="flex flex-wrap justify-between gap-3 border-b border-zinc-100 px-5 py-2.5 text-sm last:border-0"><span class="font-medium">{{ $r->name }}</span><span class="tabular-nums text-red-600">{{ Options::money($r->overdue) }} · {{ $r->invoices }} invoice(s) · oldest {{ $r->oldest_days }} days overdue</span></div>
            @empty <x-ui.empty icon="check-circle" title="No overdue invoices" /> @endforelse
        </x-ui.panel>
    </div>
</div>
