@php use App\Support\Options; @endphp
<div class="space-y-6">
    <x-ui.header title="Supplier Settlements" description="Amounts owed to farmers and suppliers for accepted produce, and the payments made against them." />

    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.stat label="Supplier liabilities" :value="Options::money($liabilities)" icon="scale" tone="red" hint="Accepted produce not yet paid for" />
        <x-ui.stat label="Paid this month" :value="Options::money($paidMonth)" icon="banknotes" />
    </div>

    <x-ui.panel>
        <div class="flex flex-wrap items-center gap-2 border-b border-zinc-200 px-4 pt-3">
            @foreach (['owed' => 'Amounts owed', 'history' => 'Payment history'] as $k => $l)
                <button type="button" wire:click="$set('tab','{{ $k }}')" @class(['-mb-px border-b-2 px-3 pb-2.5 text-sm font-medium', 'border-forest-700 text-forest-800' => $tab === $k, 'border-transparent text-zinc-500' => $tab !== $k])>{{ $l }}</button>
            @endforeach
        </div>
        <div class="border-b border-zinc-200 p-4"><div class="w-full sm:w-64"><flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search…" clearable /></div></div>

        @if ($tab === 'owed')
            @if ($owed->isEmpty())
                <x-ui.empty icon="check-circle" title="No outstanding supplier payments" text="Inspected procurements with an unpaid balance appear here." />
            @else
                <flux:table :paginate="$owed" class="px-4" pagination:class="px-4 pb-3">
                    <flux:table.columns><flux:table.column>Procurement</flux:table.column><flux:table.column>Date</flux:table.column><flux:table.column>Payee</flux:table.column><flux:table.column align="end">Amount owed</flux:table.column><flux:table.column align="end">Paid</flux:table.column><flux:table.column align="end">Balance</flux:table.column><flux:table.column>Status</flux:table.column><flux:table.column></flux:table.column></flux:table.columns>
                    <flux:table.rows>
                        @foreach ($owed as $row)
                            <flux:table.row :key="$row->id">
                                <flux:table.cell><a href="{{ route('procurement.show', $row) }}" wire:navigate class="font-medium text-forest-700 hover:underline">{{ $row->reference }}</a></flux:table.cell>
                                <flux:table.cell>{{ $row->procurement_date->format('d M Y') }}</flux:table.cell>
                                <flux:table.cell>{{ $row->sourceName() }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ Options::money($row->total_amount) }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ Options::money($row->amount_paid) }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums font-medium">{{ Options::money($row->balance()) }}</flux:table.cell>
                                <flux:table.cell><x-ui.status :value="$row->payment_status" list="PAYMENT_STATUS" /></flux:table.cell>
                                <flux:table.cell align="end">@can('settlements.manage')<flux:button size="sm" variant="primary" wire:click="openPay({{ $row->id }})">Pay</flux:button>@endcan</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        @else
            @if ($history->isEmpty())
                <x-ui.empty icon="banknotes" title="No settlements recorded" />
            @else
                <flux:table :paginate="$history" class="px-4" pagination:class="px-4 pb-3">
                    <flux:table.columns><flux:table.column>Reference</flux:table.column><flux:table.column>Date</flux:table.column><flux:table.column>Payee</flux:table.column><flux:table.column>Procurement</flux:table.column><flux:table.column>Method</flux:table.column><flux:table.column>Transaction ref</flux:table.column><flux:table.column align="end">Amount</flux:table.column><flux:table.column>Status</flux:table.column><flux:table.column></flux:table.column></flux:table.columns>
                    <flux:table.rows>
                        @foreach ($history as $row)
                            <flux:table.row :key="$row->id">
                                <flux:table.cell class="font-medium text-zinc-900">{{ $row->reference }}</flux:table.cell>
                                <flux:table.cell>{{ $row->paid_at->format('d M Y') }}</flux:table.cell>
                                <flux:table.cell>{{ $row->payeeName() }}</flux:table.cell>
                                <flux:table.cell>{{ $row->procurement->reference }}</flux:table.cell>
                                <flux:table.cell>{{ Options::label('PAYMENT_METHODS', $row->method) }}</flux:table.cell>
                                <flux:table.cell>{{ $row->transaction_ref ?: '—' }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ Options::money($row->amount) }}</flux:table.cell>
                                <flux:table.cell><x-ui.status :value="$row->status" /></flux:table.cell>
                                <flux:table.cell align="end">@can('settlements.manage')@if ($row->status === 'completed')<flux:button size="sm" variant="ghost" wire:click="askReverse({{ $row->id }})">Reverse</flux:button>@endif @endcan</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        @endif
    </x-ui.panel>

    <flux:modal wire:model.self="showForm" class="w-full max-w-md">
        @if ($target)
            <form wire:submit="save" class="space-y-4">
                <flux:heading size="lg">Pay {{ $target->sourceName() }}</flux:heading>
                <flux:subheading>{{ $target->reference }} · outstanding {{ Options::money($target->balance()) }}. Manual record; no payment API is connected.</flux:subheading>
                <flux:input type="number" step="0.01" min="0.01" wire:model="form.amount" label="Amount (KES) *" />
                <flux:select wire:model="form.method" label="Method *">@foreach (Options::PAYMENT_METHODS as $v => $l)<flux:select.option value="{{ $v }}">{{ $l }}</flux:select.option>@endforeach</flux:select>
                <flux:input wire:model="form.transaction_ref" label="Reference number" />
                <flux:input type="date" wire:model="form.paid_at" label="Payment date *" />
                <flux:input wire:model="form.notes" label="Notes" />
                <div class="flex justify-end gap-2"><flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">Cancel</flux:button><flux:button type="submit" variant="primary">Record payment</flux:button></div>
            </form>
        @endif
    </flux:modal>

    <flux:modal :open="$reversingId !== null" wire:model.self="reversingId" class="w-full max-w-md" x-on:close="$wire.set('reversingId', null)">
        @if ($reversingId)
            <form wire:submit="reverse" class="space-y-4">
                <flux:heading size="lg">Reverse settlement</flux:heading>
                <flux:input wire:model="reason" label="Reason *" />
                <div class="flex justify-end gap-2"><flux:button type="button" variant="ghost" wire:click="$set('reversingId', null)">Cancel</flux:button><flux:button type="submit" variant="danger">Reverse</flux:button></div>
            </form>
        @endif
    </flux:modal>
</div>
