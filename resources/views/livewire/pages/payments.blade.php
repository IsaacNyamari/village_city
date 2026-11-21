@php use App\Support\Options; @endphp
<div class="space-y-6">
    <x-ui.header title="Payments" description="Customer payments received against invoices. Payments are never deleted — reverse them to correct mistakes.">
        @can('payments.manage')<flux:button variant="primary" icon="plus" wire:click="create">Record payment</flux:button>@endcan
    </x-ui.header>

    <x-ui.stat label="Received this month" :value="Options::money($received)" icon="banknotes" class="max-w-xs" />

    <x-ui.panel>
        <div class="flex flex-wrap items-end gap-3 border-b border-zinc-200 p-4">
            <div class="w-full sm:w-64"><flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Reference, customer or M-Pesa code…" clearable /></div>
            <div class="w-full sm:w-48"><flux:select wire:model.live="method" aria-label="Method"><flux:select.option value="">Method: All</flux:select.option>@foreach (Options::PAYMENT_METHODS as $v => $l)<flux:select.option value="{{ $v }}">{{ $l }}</flux:select.option>@endforeach</flux:select></div>
        </div>
        @if ($rows->isEmpty())
            <x-ui.empty icon="credit-card" title="No payments recorded" text="Record a payment against an open invoice." />
        @else
            <flux:table :paginate="$rows" class="px-4" pagination:class="px-4 pb-3">
                <flux:table.columns>
                    <flux:table.column>Reference</flux:table.column><flux:table.column>Date</flux:table.column><flux:table.column>Customer</flux:table.column><flux:table.column>Invoice</flux:table.column>
                    <flux:table.column>Method</flux:table.column><flux:table.column>Transaction ref</flux:table.column><flux:table.column align="end">Amount</flux:table.column><flux:table.column>Status</flux:table.column><flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($rows as $row)
                        <flux:table.row :key="$row->id">
                            <flux:table.cell class="font-medium text-zinc-900">{{ $row->reference }}</flux:table.cell>
                            <flux:table.cell>{{ $row->paid_at->format('d M Y') }}</flux:table.cell>
                            <flux:table.cell>{{ $row->customer->name }}</flux:table.cell>
                            <flux:table.cell>@if ($row->invoice)<a href="{{ route('invoices.show', $row->invoice) }}" wire:navigate class="text-forest-700 hover:underline">{{ $row->invoice->number }}</a>@else — @endif</flux:table.cell>
                            <flux:table.cell>{{ Options::label('PAYMENT_METHODS', $row->method) }}</flux:table.cell>
                            <flux:table.cell>{{ $row->transaction_ref ?: '—' }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ Options::money($row->amount) }}</flux:table.cell>
                            <flux:table.cell><x-ui.status :value="$row->status" /></flux:table.cell>
                            <flux:table.cell align="end">@can('payments.manage')@if ($row->status === 'completed')<flux:button size="sm" variant="ghost" wire:click="askReverse({{ $row->id }})">Reverse</flux:button>@endif @endcan</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-ui.panel>

    <flux:modal wire:model.self="showForm" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">Record customer payment</flux:heading>
            <flux:subheading>Manual entry. M-Pesa (Daraja) integration is not connected.</flux:subheading>
            <flux:select wire:model.live="form.invoice_id" label="Invoice *">
                <flux:select.option value="">— Select open invoice —</flux:select.option>
                @foreach ($openInvoices as $inv)<flux:select.option value="{{ $inv->id }}">{{ $inv->number }} · {{ $inv->customer->name }} · {{ Options::money($inv->balance()) }} due</flux:select.option>@endforeach
            </flux:select>
            <flux:input type="number" step="0.01" min="0.01" wire:model="form.amount" label="Amount (KES) *" />
            <flux:select wire:model="form.method" label="Method *">@foreach (Options::PAYMENT_METHODS as $v => $l)<flux:select.option value="{{ $v }}">{{ $l }}</flux:select.option>@endforeach</flux:select>
            <flux:input wire:model="form.transaction_ref" label="Transaction reference" />
            <flux:input type="date" wire:model="form.paid_at" label="Payment date *" />
            <flux:input wire:model="form.notes" label="Notes" />
            <div class="flex justify-end gap-2"><flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">Cancel</flux:button><flux:button type="submit" variant="primary">Save payment</flux:button></div>
        </form>
    </flux:modal>

    <flux:modal :open="$reversingId !== null" wire:model.self="reversingId" class="w-full max-w-md" x-on:close="$wire.set('reversingId', null)">
        @if ($reversingId)
            <form wire:submit="reverse" class="space-y-4">
                <flux:heading size="lg">Reverse payment</flux:heading>
                <flux:subheading>The payment is marked reversed, the invoice balance is restored and a reversing ledger entry is added.</flux:subheading>
                <flux:input wire:model="reason" label="Reason *" />
                <div class="flex justify-end gap-2"><flux:button type="button" variant="ghost" wire:click="$set('reversingId', null)">Cancel</flux:button><flux:button type="submit" variant="danger">Reverse payment</flux:button></div>
            </form>
        @endif
    </flux:modal>
</div>
