@php use App\Support\Options; @endphp
<div class="space-y-6">
    <x-ui.header image="site/hero-farmer" title="Quality Control" description="Inspect and grade received produce. Only accepted quantities enter saleable stock and become payable to the supplier." />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat label="Awaiting inspection" :value="$pending->count()" icon="clock" tone="gold" />
        <x-ui.stat label="Inspections completed" :value="number_format($totals->inspections)" icon="clipboard-document-check" />
        <x-ui.stat label="Acceptance rate" :value="$totals->received > 0 ? round($totals->accepted / $totals->received * 100, 1).'%' : '—'" icon="check-badge" />
        <x-ui.stat label="Quantity rejected" :value="Options::qty($totals->rejected)" icon="x-circle" tone="red" hint="All commodities, all time" />
    </div>

    <x-ui.panel title="Awaiting inspection" description="Produce received at a centre and held until graded.">
        @if ($pending->isEmpty())
            <x-ui.empty icon="check-circle" title="Nothing waiting for inspection" text="Received procurements appear here automatically." />
        @else
            <flux:table class="px-4">
                <flux:table.columns>
                    <flux:table.column>Procurement</flux:table.column>
                    <flux:table.column>Source</flux:table.column>
                    <flux:table.column>Centre</flux:table.column>
                    <flux:table.column>Received</flux:table.column>
                    <flux:table.column>Commodities</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($pending as $row)
                        <flux:table.row :key="$row->id">
                            <flux:table.cell><a href="{{ route('procurement.show', $row) }}" wire:navigate class="font-medium text-forest-700 hover:underline">{{ $row->reference }}</a></flux:table.cell>
                            <flux:table.cell>{{ $row->sourceName() }}</flux:table.cell>
                            <flux:table.cell>{{ $row->centre->name }}</flux:table.cell>
                            <flux:table.cell>{{ $row->received_at?->diffForHumans() }}</flux:table.cell>
                            <flux:table.cell>{{ $row->items->where('received_quantity', '>', 0)->map(fn ($i) => $i->product->name.' ('.Options::qty($i->received_quantity).' '.$i->product->unit.')')->implode(', ') }}</flux:table.cell>
                            <flux:table.cell align="end">
                                @can('quality.manage')
                                    <flux:button size="sm" variant="primary" icon="clipboard-document-check" wire:click="open({{ $row->id }})">Inspect</flux:button>
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-ui.panel>

    <x-ui.panel title="Inspection history">
        <div class="flex flex-wrap items-end gap-3 border-b border-zinc-200 p-4">
            <div class="w-full sm:w-64"><flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Commodity or procurement…" clearable /></div>
            <div class="w-full sm:w-44">
                <flux:select wire:model.live="grade" aria-label="Grade">
                    <flux:select.option value="">Grade: All</flux:select.option>
                    @foreach (Options::GRADES as $value => $label)<flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>@endforeach
                </flux:select>
            </div>
        </div>
        @if ($history->isEmpty())
            <x-ui.empty icon="clipboard-document-check" title="No inspections recorded" />
        @else
            <flux:table :paginate="$history" class="px-4" pagination:class="px-4 pb-3">
                <flux:table.columns>
                    <flux:table.column>Date</flux:table.column>
                    <flux:table.column>Commodity</flux:table.column>
                    <flux:table.column>Supplier</flux:table.column>
                    <flux:table.column>Procurement</flux:table.column>
                    <flux:table.column align="end">Received</flux:table.column>
                    <flux:table.column align="end">Accepted</flux:table.column>
                    <flux:table.column align="end">Rejected</flux:table.column>
                    <flux:table.column>Grade</flux:table.column>
                    <flux:table.column>Officer</flux:table.column>
                    <flux:table.column>Comments</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($history as $row)
                        <flux:table.row :key="$row->id">
                            <flux:table.cell>{{ $row->inspected_at->format('d M Y H:i') }}</flux:table.cell>
                            <flux:table.cell class="font-medium text-zinc-900">{{ $row->product->name }}</flux:table.cell>
                            <flux:table.cell>{{ $row->procurement->sourceName() }}</flux:table.cell>
                            <flux:table.cell><a href="{{ route('procurement.show', $row->procurement) }}" wire:navigate class="text-forest-700 hover:underline">{{ $row->procurement->reference }}</a></flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ Options::qty($row->received_quantity) }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ Options::qty($row->accepted_quantity) }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ Options::qty($row->rejected_quantity) }}</flux:table.cell>
                            <flux:table.cell><x-ui.status :value="$row->grade" /></flux:table.cell>
                            <flux:table.cell>{{ $row->inspector?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell class="max-w-56 truncate" title="{{ $row->comments }}">
                                {{ $row->comments ?: '—' }}
                                @if ($row->image_path)<a class="ml-1 text-forest-700 underline" target="_blank" href="{{ Storage::disk('public')->url($row->image_path) }}">photo</a>@endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-ui.panel>

    <flux:modal wire:model.self="showInspect" class="w-full max-w-3xl">
        @if ($inspecting)
            <form wire:submit="save" class="space-y-5">
                <div>
                    <flux:heading size="lg">Inspect {{ $inspecting->reference }}</flux:heading>
                    <flux:subheading>Accepted produce is added to stock at {{ $inspecting->centre->name }}. A “Rejected” grade accepts nothing.</flux:subheading>
                </div>

                @foreach ($inspecting->items->where('received_quantity', '>', 0) as $item)
                    <div class="space-y-3 rounded-lg border border-zinc-200 p-4" wire:key="insp-{{ $item->id }}">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">{{ $item->product->name }}</span>
                            <span class="text-sm text-zinc-500">Received {{ Options::qty($item->received_quantity) }} {{ $item->product->unit }}</span>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <flux:select wire:model.live="results.{{ $item->id }}.grade" label="Grade">
                                @foreach (Options::GRADES as $value => $label)<flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>@endforeach
                            </flux:select>
                            <flux:input type="number" step="0.01" min="0" max="{{ (float) $item->received_quantity }}" wire:model="results.{{ $item->id }}.accepted" label="Accepted quantity"
                                :disabled="($results[$item->id]['grade'] ?? '') === 'rejected'" />
                            <flux:field>
                                <flux:label>Photo (optional)</flux:label>
                                <input type="file" accept="image/*" wire:model="photos.{{ $item->id }}" class="block w-full text-sm text-zinc-600 file:mr-2 file:rounded-md file:border-0 file:bg-forest-50 file:px-2 file:py-1.5 file:text-forest-800" />
                                <flux:error name="photos.{{ $item->id }}" />
                            </flux:field>
                        </div>
                        <flux:input wire:model="results.{{ $item->id }}.comments" placeholder="Inspection comments (optional)" aria-label="Comments" />
                    </div>
                @endforeach

                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="$set('showInspect', false)">Cancel</flux:button>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save,photos">Save inspection</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</div>
