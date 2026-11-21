@php use App\Support\Options; @endphp
<div class="space-y-6">
    <x-ui.header image="site/market-stall" title="Inventory" description="Batch-level stock at every centre and warehouse. Every change is recorded as a permanent movement; stock is issued oldest-batch-first (FIFO)." />

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Stock value (at cost)" :value="Options::money($stockValue)" icon="scale" />
        <x-ui.stat label="Low-stock products" :value="$lowStock->count()" icon="exclamation-triangle" :tone="$lowStock->count() ? 'red' : 'forest'" hint="At or below minimum level" />
        <x-ui.stat label="Batches expiring ≤ 2 days" :value="$expiring" icon="clock" :tone="$expiring ? 'gold' : 'forest'" hint="Includes already expired" />
    </div>

    <x-ui.panel>
        <div class="flex flex-wrap items-center gap-2 border-b border-zinc-200 px-4 pt-3">
            @foreach (['stock' => 'Stock by product', 'batches' => 'Batches', 'movements' => 'Movement history'] as $key => $label)
                <button type="button" wire:click="$set('tab', '{{ $key }}')"
                    @class(['-mb-px border-b-2 px-3 pb-2.5 text-sm font-medium', 'border-forest-700 text-forest-800' => $tab === $key, 'border-transparent text-zinc-500 hover:text-zinc-800' => $tab !== $key])>{{ $label }}</button>
            @endforeach
        </div>

        <div class="flex flex-wrap items-end gap-3 border-b border-zinc-200 p-4">
            <div class="w-full sm:w-64"><flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search product{{ $tab === 'batches' ? ' or batch' : '' }}…" clearable /></div>
            <div class="w-full sm:w-56">
                <flux:select wire:model.live="centre" aria-label="Location">
                    <flux:select.option value="">Location: All</flux:select.option>
                    @foreach ($centres as $id => $name)<flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>@endforeach
                </flux:select>
            </div>
            @if ($tab === 'movements')
                <div class="w-full sm:w-52">
                    <flux:select wire:model.live="type" aria-label="Movement type">
                        <flux:select.option value="">Type: All</flux:select.option>
                        @foreach (Options::MOVEMENT_TYPES as $value => $label)<flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>@endforeach
                    </flux:select>
                </div>
            @endif
        </div>

        @if ($tab === 'stock')
            @if ($stock->isEmpty())
                <x-ui.empty icon="archive-box" title="No products found" />
            @else
                <flux:table class="px-4">
                    <flux:table.columns>
                        <flux:table.column>Product</flux:table.column>
                        <flux:table.column>Category</flux:table.column>
                        <flux:table.column align="end">Saleable</flux:table.column>
                        <flux:table.column align="end">Expired (unsaleable)</flux:table.column>
                        <flux:table.column align="end">Minimum</flux:table.column>
                        <flux:table.column align="end">Locations</flux:table.column>
                        <flux:table.column align="end">Value at cost</flux:table.column>
                        <flux:table.column>Level</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($stock as $product)
                            <flux:table.row :key="$product->id">
                                <flux:table.cell class="font-medium text-zinc-900">{{ $product->name }}</flux:table.cell>
                                <flux:table.cell>{{ $product->category?->name }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ Options::qty($product->saleable) }} {{ $product->unit }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums {{ $product->expired > 0 ? 'text-red-600' : '' }}">{{ Options::qty($product->expired) }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ Options::qty($product->min_stock_level) }}</flux:table.cell>
                                <flux:table.cell align="end">{{ $product->locations }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ Options::money($product->value) }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($product->saleable <= 0)
                                        <flux:badge size="sm" color="red" inset="top bottom">Out of stock</flux:badge>
                                    @elseif ($centre === '' && (float) $product->min_stock_level > 0 && $product->saleable <= (float) $product->min_stock_level)
                                        <flux:badge size="sm" color="amber" inset="top bottom">Low</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="green" inset="top bottom">In stock</flux:badge>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        @elseif ($tab === 'batches')
            @if ($batches->isEmpty())
                <x-ui.empty icon="archive-box" title="No stock batches on hand" text="Batches are created when inspected produce is accepted into stock." />
            @else
                <flux:table :paginate="$batches" class="px-4" pagination:class="px-4 pb-3">
                    <flux:table.columns>
                        <flux:table.column>Batch</flux:table.column>
                        <flux:table.column>Product</flux:table.column>
                        <flux:table.column>Location</flux:table.column>
                        <flux:table.column>Received</flux:table.column>
                        <flux:table.column>Expiry</flux:table.column>
                        <flux:table.column>Grade</flux:table.column>
                        <flux:table.column align="end">Remaining</flux:table.column>
                        <flux:table.column align="end">Purchase price</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($batches as $b)
                            <flux:table.row :key="$b->id">
                                <flux:table.cell class="font-medium text-zinc-900">{{ $b->batch_number }}</flux:table.cell>
                                <flux:table.cell>{{ $b->product->name }}</flux:table.cell>
                                <flux:table.cell>{{ $b->centre->name }}</flux:table.cell>
                                <flux:table.cell>{{ $b->received_at->format('d M Y') }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($b->expiry_date)
                                        <span @class(['text-red-600 font-medium' => $b->isExpired()])>{{ $b->expiry_date->format('d M Y') }}{{ $b->isExpired() ? ' · expired' : '' }}</span>
                                    @else — @endif
                                </flux:table.cell>
                                <flux:table.cell><x-ui.status :value="$b->grade" /></flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ Options::qty($b->quantity) }} / {{ Options::qty($b->initial_quantity) }} {{ $b->product->unit }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ Options::money($b->purchase_price) }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    @can('inventory.manage')
                                        <flux:dropdown position="bottom" align="end">
                                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" aria-label="Actions" />
                                            <flux:menu>
                                                <flux:menu.item icon="arrows-right-left" wire:click="open({{ $b->id }}, 'transfer')">Transfer</flux:menu.item>
                                                <flux:menu.item icon="adjustments-horizontal" wire:click="open({{ $b->id }}, 'adjust')">Adjust count</flux:menu.item>
                                                <flux:menu.item icon="trash" variant="danger" wire:click="open({{ $b->id }}, 'writeoff')">Write off</flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    @endcan
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        @else
            @if ($movements->isEmpty())
                <x-ui.empty icon="arrows-right-left" title="No stock movements recorded" />
            @else
                <flux:table :paginate="$movements" class="px-4" pagination:class="px-4 pb-3">
                    <flux:table.columns>
                        <flux:table.column>Date</flux:table.column>
                        <flux:table.column>Type</flux:table.column>
                        <flux:table.column>Product</flux:table.column>
                        <flux:table.column>Location</flux:table.column>
                        <flux:table.column>Batch</flux:table.column>
                        <flux:table.column align="end">Quantity</flux:table.column>
                        <flux:table.column>By</flux:table.column>
                        <flux:table.column>Notes</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($movements as $m)
                            <flux:table.row :key="$m->id">
                                <flux:table.cell>{{ $m->created_at->format('d M Y H:i') }}</flux:table.cell>
                                <flux:table.cell><x-ui.status :value="$m->type" list="MOVEMENT_TYPES" /></flux:table.cell>
                                <flux:table.cell class="font-medium text-zinc-900">{{ $m->product->name }}</flux:table.cell>
                                <flux:table.cell>{{ $m->centre->name }}</flux:table.cell>
                                <flux:table.cell>{{ $m->batch?->batch_number ?? '—' }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums font-medium {{ $m->quantity < 0 ? 'text-red-600' : 'text-forest-700' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ Options::qty($m->quantity) }}</flux:table.cell>
                                <flux:table.cell>{{ $m->user?->name ?? 'System' }}</flux:table.cell>
                                <flux:table.cell class="max-w-64 truncate" title="{{ $m->notes }}">{{ $m->notes ?: '—' }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        @endif
    </x-ui.panel>

    <flux:modal wire:model.self="showAction" class="w-full max-w-md">
        @if ($batch)
            <form wire:submit="submit" class="space-y-5">
                <div>
                    <flux:heading size="lg">{{ ['adjust' => 'Adjust stock count', 'writeoff' => 'Write off stock', 'transfer' => 'Transfer stock'][$action] }}</flux:heading>
                    <flux:subheading>{{ $batch->product->name }} · batch {{ $batch->batch_number }} at {{ $batch->centre->name }} · {{ Options::qty($batch->quantity) }} {{ $batch->product->unit }} remaining</flux:subheading>
                </div>

                @if ($action === 'adjust')
                    <flux:select wire:model="form.direction" label="Adjustment">
                        <flux:select.option value="decrease">Decrease (count is lower than recorded)</flux:select.option>
                        <flux:select.option value="increase">Increase (count is higher than recorded)</flux:select.option>
                    </flux:select>
                @elseif ($action === 'writeoff')
                    <flux:select wire:model="form.type" label="Reason category">
                        @foreach (Options::WRITE_OFF_TYPES as $value => $label)<flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>@endforeach
                    </flux:select>
                @else
                    <flux:select wire:model="form.destination" label="Destination">
                        <flux:select.option value="">— Select —</flux:select.option>
                        @foreach ($centres as $id => $name)
                            @if ($id !== $batch->collection_centre_id)<flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>@endif
                        @endforeach
                    </flux:select>
                @endif

                <flux:input type="number" step="0.01" min="0.01" wire:model="form.quantity" label="Quantity ({{ $batch->product->unit }})" />
                <flux:input wire:model="form.notes" :label="$action === 'transfer' ? 'Notes (optional)' : 'Reason'" />

                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="$set('showAction', false)">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Record movement</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</div>
