@php
    use App\Support\Options;
    use App\Enums\ProcurementStatus as S;
    $p = $procurement;
@endphp
<div class="space-y-6">
    <x-ui.header :title="'Procurement '.$p->reference" :back="route('procurement.index')"
        :description="$p->sourceName().' → '.$p->centre->name.' · '.$p->procurement_date->format('d M Y')">
        <x-ui.status :value="$p->status" />
        @can('procurement.manage')
            @if ($p->status === S::Requested)
                <flux:button icon="user-plus" wire:click="openAssign">{{ $p->farmer_id || $p->supplier_id ? 'Change source' : 'Assign source' }}</flux:button>
                <flux:button variant="primary" icon="check" wire:click="confirm">Confirm purchase</flux:button>
            @elseif ($p->status === S::Confirmed)
                <flux:button variant="primary" icon="inbox-arrow-down" wire:click="openReceive">Record produce received</flux:button>
            @endif
            @if (in_array($p->status, [S::Requested, S::Confirmed]))
                <flux:button variant="danger" icon="x-mark" wire:click="cancel" wire:confirm="Cancel this procurement?">Cancel</flux:button>
            @endif
        @endcan
        @if ($p->status === S::Received)
            @can('quality.manage')
                <flux:button variant="primary" icon="clipboard-document-check" :href="route('quality.index', ['inspect' => $p->id])" wire:navigate>Inspect &amp; grade</flux:button>
            @endcan
        @endif
        @if ($p->status === S::Inspected && $p->balance() > 0)
            @can('settlements.manage')
                <flux:button variant="primary" icon="banknotes" :href="route('settlements.index', ['pay' => $p->id])" wire:navigate>Pay supplier</flux:button>
            @endcan
        @endif
    </x-ui.header>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat label="Ordered value" :value="Options::money($p->orderedValue())" icon="clipboard-document-list" tone="zinc" hint="Before inspection" />
        <x-ui.stat label="Payable (accepted)" :value="$p->status === S::Inspected ? Options::money($p->total_amount) : 'Pending inspection'" icon="scale" />
        <x-ui.stat label="Paid" :value="Options::money($p->amount_paid)" icon="banknotes" tone="gold" />
        <x-ui.stat label="Outstanding" :value="Options::money($p->status === S::Inspected ? $p->balance() : 0)" icon="exclamation-circle" :tone="$p->balance() > 0 ? 'red' : 'forest'" />
    </div>

    <x-ui.panel title="Details" padded>
        <x-ui.dl :items="[
            'Source' => $p->sourceName().' ('.$p->sourceType().')',
            'Source county' => $p->farmer?->county?->name ?? $p->supplier?->county?->name,
            'Collection centre' => $p->centre->name,
            'Procurement date' => $p->procurement_date->format('d M Y'),
            'Linked order' => $p->order?->reference,
            'Created by' => $p->creator?->name ?? 'System',
            'Received at' => $p->received_at?->format('d M Y H:i'),
            'Inspected at' => $p->inspected_at?->format('d M Y H:i'),
            'Notes' => $p->notes,
        ]" />
    </x-ui.panel>

    <x-ui.panel title="Commodities">
        <flux:table class="px-4">
            <flux:table.columns>
                <flux:table.column>Commodity</flux:table.column>
                <flux:table.column align="end">Ordered</flux:table.column>
                <flux:table.column align="end">Unit price</flux:table.column>
                <flux:table.column align="end">Received</flux:table.column>
                <flux:table.column align="end">Accepted</flux:table.column>
                <flux:table.column align="end">Rejected</flux:table.column>
                <flux:table.column>Grade</flux:table.column>
                <flux:table.column align="end">Payable</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($p->items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell class="font-medium text-zinc-900">{{ $item->product->name }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ Options::qty($item->quantity) }} {{ $item->product->unit }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ Options::money($item->unit_price) }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $p->received_at ? Options::qty($item->received_quantity) : '—' }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $p->inspected_at ? Options::qty($item->accepted_quantity) : '—' }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $p->inspected_at ? Options::qty($item->rejected_quantity) : '—' }}</flux:table.cell>
                        <flux:table.cell><x-ui.status :value="$item->grade" /></flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $p->inspected_at ? Options::money($item->total) : '—' }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </x-ui.panel>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.panel title="Quality inspections">
            @forelse ($p->inspections as $inspection)
                <div class="border-b border-zinc-100 px-5 py-3 last:border-0">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm font-medium">{{ $inspection->product->name }}</span>
                        <x-ui.status :value="$inspection->grade" />
                    </div>
                    <p class="text-xs text-zinc-500">Accepted {{ Options::qty($inspection->accepted_quantity) }} of {{ Options::qty($inspection->received_quantity) }} · {{ $inspection->inspector?->name ?? 'Unknown' }} · {{ $inspection->inspected_at->format('d M Y H:i') }}</p>
                    @if ($inspection->comments)<p class="mt-1 text-sm text-zinc-700">{{ $inspection->comments }}</p>@endif
                    @if ($inspection->image_path)<a class="text-xs text-forest-700 underline" target="_blank" href="{{ Storage::disk('public')->url($inspection->image_path) }}">View photo</a>@endif
                </div>
            @empty
                <x-ui.empty icon="clipboard-document-check" title="Not inspected yet" text="Inspection results appear here once the produce has been graded." />
            @endforelse
        </x-ui.panel>

        <x-ui.panel title="Payments to supplier">
            @forelse ($p->settlements as $settlement)
                <div class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-3 last:border-0">
                    <div>
                        <p class="text-sm font-medium">{{ $settlement->reference }} · {{ Options::label('PAYMENT_METHODS', $settlement->method) }}</p>
                        <p class="text-xs text-zinc-500">{{ $settlement->paid_at->format('d M Y') }}{{ $settlement->transaction_ref ? ' · Ref '.$settlement->transaction_ref : '' }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold tabular-nums">{{ Options::money($settlement->amount) }}</p>
                        <x-ui.status :value="$settlement->status" />
                    </div>
                </div>
            @empty
                <x-ui.empty icon="banknotes" title="No payments recorded" text="Supplier payments are recorded under Finance → Supplier Settlements." />
            @endforelse
        </x-ui.panel>
    </div>

    <flux:modal wire:model.self="showReceive" class="w-full max-w-xl">
        <form wire:submit="receive" class="space-y-5">
            <div>
                <flux:heading size="lg">Record produce received</flux:heading>
                <flux:subheading>Enter the quantity delivered to {{ $p->centre->name }}. Received produce is held until quality inspection.</flux:subheading>
            </div>
            @foreach ($p->items as $item)
                <flux:input type="number" step="0.01" min="0" wire:model="received.{{ $item->id }}" wire:key="rcv-{{ $item->id }}"
                    :label="$item->product->name.' — ordered '.Options::qty($item->quantity).' '.$item->product->unit" />
            @endforeach
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showReceive', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save received quantities</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showAssign" class="w-full max-w-md">
        <form wire:submit="assign" class="space-y-5">
            <flux:heading size="lg">Assign farmer or supplier</flux:heading>
            <flux:select wire:model.live="sourceType" label="Source type">
                <flux:select.option value="farmer">Farmer</flux:select.option>
                <flux:select.option value="supplier">Supplier / cooperative</flux:select.option>
            </flux:select>
            <flux:select wire:model="sourceId" :label="$sourceType === 'farmer' ? 'Farmer' : 'Supplier'">
                <flux:select.option value="">— Select —</flux:select.option>
                @foreach ($sourceType === 'farmer' ? $farmers : $suppliers as $id => $name)
                    <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                @endforeach
            </flux:select>
            @can('intelligence.view')
                <flux:text class="text-xs">Tip: Market Intelligence ranks farmers and suppliers for each commodity.</flux:text>
            @endcan
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showAssign', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Assign</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
