@php use App\Support\Options; @endphp
<div class="space-y-6">
    <x-ui.header image="site/hero-harvest" title="Procurement" description="Demand-driven purchasing: request produce, confirm the price, receive it at a centre, then inspect it. Only accepted quantities are paid for and stocked.">
        @can('intelligence.view')
            <flux:button :href="route('forecasting')" icon="light-bulb" wire:navigate>Recommendations</flux:button>
        @endcan
        @can('procurement.manage')
            <flux:button variant="primary" icon="plus" wire:click="create">New procurement</flux:button>
        @endcan
    </x-ui.header>

    <x-ui.panel>
        <div class="flex flex-wrap items-end gap-3 border-b border-zinc-200 p-4">
            <div class="w-full sm:w-64"><flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Reference, farmer or supplier…" clearable /></div>
            <div class="w-full sm:w-52">
                <flux:select wire:model.live="status" aria-label="Status">
                    <flux:select.option value="">Status: All</flux:select.option>
                    @foreach ($statuses as $s)<flux:select.option value="{{ $s->value }}">{{ $s->label() }}</flux:select.option>@endforeach
                </flux:select>
            </div>
            <div class="w-full sm:w-48">
                <flux:select wire:model.live="centre" aria-label="Centre">
                    <flux:select.option value="">Centre: All</flux:select.option>
                    @foreach ($centres as $id => $name)<flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>@endforeach
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
            <x-ui.empty icon="clipboard-document-list" title="No procurements found" text="Create a procurement request to start sourcing produce from farmers and suppliers." />
        @else
            <flux:table :paginate="$rows" class="px-4" pagination:class="px-4 pb-3">
                <flux:table.columns>
                    <flux:table.column>Reference</flux:table.column>
                    <flux:table.column>Date</flux:table.column>
                    <flux:table.column>Farmer / supplier</flux:table.column>
                    <flux:table.column>Centre</flux:table.column>
                    <flux:table.column>Commodities</flux:table.column>
                    <flux:table.column align="end">Payable</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Payment</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($rows as $row)
                        <flux:table.row :key="$row->id">
                            <flux:table.cell>
                                <a href="{{ route('procurement.show', $row) }}" wire:navigate class="font-medium text-forest-700 hover:underline">{{ $row->reference }}</a>
                                @if ($row->order)<div class="text-xs text-zinc-500">for {{ $row->order->reference }}</div>@endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $row->procurement_date->format('d M Y') }}</flux:table.cell>
                            <flux:table.cell>{{ $row->sourceName() }} <span class="text-xs text-zinc-500">{{ $row->sourceType() !== '—' ? '· '.$row->sourceType() : '' }}</span></flux:table.cell>
                            <flux:table.cell>{{ $row->centre->name }}</flux:table.cell>
                            <flux:table.cell>{{ $row->items->map(fn ($i) => $i->product->name)->take(3)->implode(', ') }}{{ $row->items->count() > 3 ? '…' : '' }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">
                                @if ($row->status === \App\Enums\ProcurementStatus::Inspected)
                                    {{ Options::money($row->total_amount) }}
                                @else
                                    <span class="text-zinc-500" title="Ordered value — the payable amount is set at inspection">~{{ Options::money($row->orderedValue()) }}</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell><x-ui.status :value="$row->status" /></flux:table.cell>
                            <flux:table.cell>@if ($row->status === \App\Enums\ProcurementStatus::Inspected)<x-ui.status :value="$row->payment_status" list="PAYMENT_STATUS" />@else<span class="text-zinc-400">—</span>@endif</flux:table.cell>
                            <flux:table.cell align="end">
                                <div class="flex justify-end gap-1">
                                    @if ($row->status === \App\Enums\ProcurementStatus::Requested)
                                        @can('procurement.manage')<flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $row->id }})" aria-label="Edit" />@endcan
                                    @endif
                                    <flux:button size="sm" variant="ghost" icon="eye" :href="route('procurement.show', $row)" wire:navigate aria-label="View" />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-ui.panel>

    <flux:modal wire:model.self="showForm" class="w-full max-w-4xl">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit procurement request' : 'New procurement request' }}</flux:heading>
                <flux:subheading>Agree quantities and buying prices. Quantities actually paid for are confirmed at quality inspection.</flux:subheading>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model.live="form.source_type" label="Source type *">
                    <flux:select.option value="farmer">Farmer</flux:select.option>
                    <flux:select.option value="supplier">Supplier / cooperative</flux:select.option>
                </flux:select>

                @if (($form['source_type'] ?? 'farmer') === 'farmer')
                    <flux:select wire:model="form.farmer_id" label="Farmer" description="Can be assigned later, before confirmation.">
                        <flux:select.option value="">— To be assigned —</flux:select.option>
                        @foreach ($farmers as $farmer)<flux:select.option value="{{ $farmer->id }}">{{ $farmer->name }} ({{ $farmer->number }})</flux:select.option>@endforeach
                    </flux:select>
                @else
                    <flux:select wire:model="form.supplier_id" label="Supplier" description="Can be assigned later, before confirmation.">
                        <flux:select.option value="">— To be assigned —</flux:select.option>
                        @foreach ($suppliers as $supplier)<flux:select.option value="{{ $supplier->id }}">{{ $supplier->business_name }} ({{ $supplier->number }})</flux:select.option>@endforeach
                    </flux:select>
                @endif

                <flux:select wire:model="form.collection_centre_id" label="Collection centre *">
                    <flux:select.option value="">— Select —</flux:select.option>
                    @foreach ($centres as $id => $name)<flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>@endforeach
                </flux:select>
                <flux:input type="date" wire:model="form.procurement_date" label="Procurement date *" />
            </div>

            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <flux:heading>Commodities</flux:heading>
                    <flux:button type="button" size="sm" icon="plus" wire:click="addItem">Add commodity</flux:button>
                </div>
                <flux:error name="items" />

                @foreach ($items as $index => $item)
                    <div class="grid grid-cols-12 items-start gap-2 rounded-lg border border-zinc-200 p-3" wire:key="pitem-{{ $index }}">
                        <div class="col-span-12 sm:col-span-5">
                            <flux:select wire:model.live="items.{{ $index }}.product_id" aria-label="Commodity">
                                <flux:select.option value="">— Commodity —</flux:select.option>
                                @foreach ($products as $product)<flux:select.option value="{{ $product->id }}">{{ $product->name }} ({{ $product->unit }})</flux:select.option>@endforeach
                            </flux:select>
                            <flux:error name="items.{{ $index }}.product_id" />
                        </div>
                        <div class="col-span-5 sm:col-span-3">
                            <flux:input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="items.{{ $index }}.quantity" placeholder="Quantity" aria-label="Quantity" />
                            <flux:error name="items.{{ $index }}.quantity" />
                        </div>
                        <div class="col-span-5 sm:col-span-3">
                            <flux:input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="items.{{ $index }}.unit_price" placeholder="Unit price" aria-label="Unit price" />
                            <flux:error name="items.{{ $index }}.unit_price" />
                        </div>
                        <div class="col-span-2 flex justify-end sm:col-span-1">
                            <flux:button type="button" variant="ghost" icon="trash" wire:click="removeItem({{ $index }})" aria-label="Remove" :disabled="count($items) === 1" />
                        </div>
                    </div>
                @endforeach

                <div class="flex justify-end text-sm">
                    <span class="text-zinc-500">Ordered value:&nbsp;</span>
                    <span class="font-semibold tabular-nums">{{ Options::money(collect($items)->sum(fn ($i) => (float) ($i['quantity'] ?: 0) * (float) ($i['unit_price'] ?: 0))) }}</span>
                </div>
            </div>

            <flux:textarea wire:model="form.notes" label="Notes" rows="2" />

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">Save procurement</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
