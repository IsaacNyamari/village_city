@php use App\Support\Options; $i = $invoice; $o = $i->order; @endphp
<div class="space-y-6">
    <x-ui.header :title="'Invoice '.$i->number" :back="route('invoices.index')" :description="$i->customer->name">
        <x-ui.status :value="$i->status" list="PAYMENT_STATUS" />
        <flux:button icon="printer" onclick="window.print()">Print</flux:button>
        @can('payments.manage')
            @if ($i->status !== 'void' && $i->balance() > 0)
                <flux:button variant="primary" icon="banknotes" wire:click="openPay">Record payment</flux:button>
            @endif
        @endcan
    </x-ui.header>

    <x-ui.panel padded class="print:border-0 print:shadow-none">
        <div class="flex flex-wrap justify-between gap-6">
            <div>
                <p class="text-lg font-semibold text-forest-800">{{ $company['name'] }}</p>
                <p class="text-sm text-zinc-500">{{ $company['address'] }} {{ $company['phone'] }} {{ $company['email'] }}</p>
            </div>
            <div class="text-sm sm:text-right">
                <p><span class="text-zinc-500">Invoice</span> <strong>{{ $i->number }}</strong></p>
                <p><span class="text-zinc-500">Issued</span> {{ $i->issue_date->format('d M Y') }}</p>
                <p><span class="text-zinc-500">Due</span> {{ $i->due_date?->format('d M Y') }}</p>
                <p><span class="text-zinc-500">Order</span> {{ $o->reference }}</p>
            </div>
        </div>
        <div class="mt-6 text-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Bill to</p>
            <p class="font-medium">{{ $i->customer->name }}</p>
            <p class="text-zinc-600">{{ $i->customer->billing_address ?: $i->customer->delivery_address }}</p>
        </div>
        <div class="mt-6 overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-zinc-200 text-left text-xs uppercase text-zinc-500"><th class="py-2">Item</th><th class="py-2 text-right">Qty</th><th class="py-2 text-right">Unit price</th><th class="py-2 text-right">Amount</th></tr></thead>
                <tbody>
                    @foreach ($o->items as $item)
                        <tr class="border-b border-zinc-100"><td class="py-2">{{ $item->product->name }}</td><td class="py-2 text-right tabular-nums">{{ Options::qty($item->quantity) }} {{ $item->product->unit }}</td><td class="py-2 text-right tabular-nums">{{ Options::money($item->unit_price) }}</td><td class="py-2 text-right tabular-nums">{{ Options::money($item->total) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <dl class="ml-auto mt-4 w-full max-w-xs space-y-1 text-sm">
            <div class="flex justify-between"><dt class="text-zinc-500">Subtotal</dt><dd class="tabular-nums">{{ Options::money($o->subtotal) }}</dd></div>
            <div class="flex justify-between"><dt class="text-zinc-500">Discount</dt><dd class="tabular-nums">− {{ Options::money($o->discount) }}</dd></div>
            <div class="flex justify-between"><dt class="text-zinc-500">Delivery</dt><dd class="tabular-nums">{{ Options::money($o->delivery_fee) }}</dd></div>
            <div class="flex justify-between border-t pt-1 font-semibold"><dt>Total</dt><dd class="tabular-nums">{{ Options::money($i->total) }}</dd></div>
            <div class="flex justify-between"><dt class="text-zinc-500">Paid</dt><dd class="tabular-nums">{{ Options::money($i->amount_paid) }}</dd></div>
            <div class="flex justify-between font-semibold"><dt>Balance due</dt><dd class="tabular-nums">{{ Options::money($i->status === 'void' ? 0 : $i->balance()) }}</dd></div>
        </dl>
    </x-ui.panel>

    <x-ui.panel title="Payments" class="print:hidden">
        @forelse ($i->payments as $payment)
            <div class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-3 last:border-0">
                <div><p class="text-sm font-medium">{{ $payment->reference }} · {{ Options::label('PAYMENT_METHODS', $payment->method) }}</p><p class="text-xs text-zinc-500">{{ $payment->paid_at->format('d M Y') }}{{ $payment->transaction_ref ? ' · Ref '.$payment->transaction_ref : '' }}</p></div>
                <div class="text-right"><p class="text-sm font-semibold tabular-nums">{{ Options::money($payment->amount) }}</p><x-ui.status :value="$payment->status" /></div>
            </div>
        @empty
            <x-ui.empty icon="banknotes" title="No payments yet" />
        @endforelse
    </x-ui.panel>

    <flux:modal wire:model.self="showPay" class="w-full max-w-md">
        <form wire:submit="pay" class="space-y-4">
            <flux:heading size="lg">Record payment</flux:heading>
            <flux:subheading>Outstanding: {{ Options::money($i->balance()) }}. Payments are recorded manually; no payment gateway is connected.</flux:subheading>
            <flux:input type="number" step="0.01" min="0.01" wire:model="form.amount" label="Amount (KES) *" />
            <flux:select wire:model="form.method" label="Method *">@foreach (Options::PAYMENT_METHODS as $v => $l)<flux:select.option value="{{ $v }}">{{ $l }}</flux:select.option>@endforeach</flux:select>
            <flux:input wire:model="form.transaction_ref" label="Transaction reference (e.g. M-Pesa code)" />
            <flux:input type="date" wire:model="form.paid_at" label="Payment date *" />
            <flux:input wire:model="form.notes" label="Notes" />
            <div class="flex justify-end gap-2"><flux:button type="button" variant="ghost" wire:click="$set('showPay', false)">Cancel</flux:button><flux:button type="submit" variant="primary">Save payment</flux:button></div>
        </form>
    </flux:modal>
</div>
