@php use App\Support\Options; @endphp
<div class="space-y-6">
    <x-ui.header image="site/produce-stall" :title="$staff ? 'Orders' : 'My Orders'"
        :description="$staff ? 'Customer orders from confirmation through stock allocation, dispatch and delivery.' : 'Track the progress of the orders you have placed.'">
        @can('marketplace.view')
            <flux:button :href="route('marketplace')" icon="shopping-bag" wire:navigate>Marketplace</flux:button>
        @endcan
        @can('orders.manage')
            <flux:button variant="primary" icon="plus" wire:click="create">New order</flux:button>
        @endcan
    </x-ui.header>

    <x-ui.panel>
        <div class="flex flex-wrap items-end gap-3 border-b border-zinc-200 p-4">
            <div class="w-full sm:w-64"><flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Order reference{{ $staff ? ' or customer' : '' }}…" clearable /></div>
            <div class="w-full sm:w-52">
                <flux:select wire:model.live="status" aria-label="Status">
                    <flux:select.option value="">Status: All</flux:select.option>
                    @foreach ($statuses as $s)<flux:select.option value="{{ $s->value }}">{{ $s->label() }}</flux:select.option>@endforeach
                </flux:select>
            </div>
            <div class="w-full sm:w-44">
                <flux:select wire:model.live="payment" aria-label="Payment">
                    <flux:select.option value="">Payment: All</flux:select.option>
                    @foreach (Options::PAYMENT_STATUS as $value => $label)<flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>@endforeach
                </flux:select>
            </div>
        </div>

        @if ($rows->isEmpty())
            <x-ui.empty icon="shopping-cart" title="No orders found" :text="$staff ? 'Orders placed in the marketplace or created here will appear in this list.' : 'Browse the marketplace to place your first order.'" />
        @else
            <flux:table :paginate="$rows" class="px-4" pagination:class="px-4 pb-3">
                <flux:table.columns>
                    <flux:table.column>Order</flux:table.column>
                    <flux:table.column>Date</flux:table.column>
                    @if ($staff)<flux:table.column>Customer</flux:table.column>@endif
                    <flux:table.column align="end">Items</flux:table.column>
                    <flux:table.column align="end">Total</flux:table.column>
                    <flux:table.column>Delivery date</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Payment</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($rows as $row)
                        <flux:table.row :key="$row->id">
                            <flux:table.cell><a href="{{ route('orders.show', $row) }}" wire:navigate class="font-medium text-forest-700 hover:underline">{{ $row->reference }}</a></flux:table.cell>
                            <flux:table.cell>{{ $row->created_at->format('d M Y') }}</flux:table.cell>
                            @if ($staff)<flux:table.cell>{{ $row->customer->name }}</flux:table.cell>@endif
                            <flux:table.cell align="end">{{ $row->items->count() }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ Options::money($row->total) }}</flux:table.cell>
                            <flux:table.cell>{{ $row->expected_delivery_date?->format('d M Y') ?? '—' }}</flux:table.cell>
                            <flux:table.cell><x-ui.status :value="$row->status" /></flux:table.cell>
                            <flux:table.cell><x-ui.status :value="$row->payment_status" list="PAYMENT_STATUS" /></flux:table.cell>
                            <flux:table.cell align="end"><flux:button size="sm" variant="ghost" icon="eye" :href="route('orders.show', $row)" wire:navigate aria-label="View" /></flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-ui.panel>

    <flux:modal wire:model.self="showForm" class="w-full max-w-4xl">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">New order</flux:heading>
                <flux:subheading>Wholesale and institutional orders. Stock is allocated when the order is confirmed; shortfalls raise a procurement request.</flux:subheading>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model.live="form.customer_id" label="Customer *">
                    <flux:select.option value="">— Select —</flux:select.option>
                    @foreach ($customers as $customer)<flux:select.option value="{{ $customer->id }}">{{ $customer->name }} ({{ $customer->number }})</flux:select.option>@endforeach
                </flux:select>
                <flux:input type="date" wire:model="form.expected_delivery_date" label="Expected delivery date *" />
                <div class="sm:col-span-2"><flux:input wire:model="form.delivery_address" label="Delivery address *" /></div>
            </div>

            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <flux:heading>Items</flux:heading>
                    <flux:button type="button" size="sm" icon="plus" wire:click="addItem">Add item</flux:button>
                </div>

                @foreach ($items as $index => $item)
                    @php $available = $item['product_id'] ? ($stock[$item['product_id']] ?? 0) : null; @endphp
                    <div class="rounded-lg border border-zinc-200 p-3" wire:key="oitem-{{ $index }}">
                        <div class="grid grid-cols-12 items-start gap-2">
                            <div class="col-span-12 sm:col-span-4">
                                <flux:select wire:model.live="items.{{ $index }}.product_id" aria-label="Product">
                                    <flux:select.option value="">— Product —</flux:select.option>
                                    @foreach ($products as $product)<flux:select.option value="{{ $product->id }}">{{ $product->name }} ({{ $product->unit }})</flux:select.option>@endforeach
                                </flux:select>
                                <flux:error name="items.{{ $index }}.product_id" />
                            </div>
                            <div class="col-span-4 sm:col-span-2">
                                <flux:input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="items.{{ $index }}.quantity" placeholder="Qty" aria-label="Quantity" />
                                <flux:error name="items.{{ $index }}.quantity" />
                            </div>
                            <div class="col-span-4 sm:col-span-2">
                                <flux:input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="items.{{ $index }}.unit_price" placeholder="Price" aria-label="Unit price" />
                                <flux:error name="items.{{ $index }}.unit_price" />
                            </div>
                            <div class="col-span-4 sm:col-span-3">
                                <flux:input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="items.{{ $index }}.discount" placeholder="Discount" aria-label="Line discount" />
                            </div>
                            <div class="col-span-12 flex justify-end sm:col-span-1">
                                <flux:button type="button" variant="ghost" icon="trash" wire:click="removeItem({{ $index }})" aria-label="Remove" :disabled="count($items) === 1" />
                            </div>
                        </div>
                        @if ($available !== null)
                            <p class="mt-1.5 text-xs {{ (float) ($item['quantity'] ?: 0) > $available ? 'text-gold-600' : 'text-zinc-500' }}">
                                {{ Options::qty($available) }} in stock{{ (float) ($item['quantity'] ?: 0) > $available ? ' — shortfall of '.Options::qty((float) $item['quantity'] - $available).' will need procurement' : '' }}
                            </p>
                        @endif
                    </div>
                @endforeach
                <flux:error name="items" />
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="form.discount" label="Order discount (KES)" />
                <flux:input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="form.delivery_fee" label="Delivery charge (KES)" />
                <div class="flex flex-col justify-end rounded-lg bg-forest-50 px-4 py-2">
                    <span class="text-xs uppercase tracking-wide text-forest-700">Grand total</span>
                    <span class="text-lg font-semibold tabular-nums text-forest-900">{{ Options::money(max(0, $formTotal)) }}</span>
                </div>
            </div>

            <flux:textarea wire:model="form.instructions" label="Special instructions" rows="2" />

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">Create order</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
