<div class="relative max-sm:hidden" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
    <flux:input wire:model.live.debounce.300ms="term" @focus="open = true" @input="open = true" icon="magnifying-glass" placeholder="Search farmers, orders, products…" size="sm" class="w-64 lg:w-80" aria-label="Global search" />

    <div x-show="open && $wire.term.length >= 2" x-cloak class="absolute right-0 z-30 mt-2 max-h-96 w-96 max-w-[90vw] overflow-y-auto rounded-xl border border-zinc-200 bg-white p-2 shadow-lg">
        @forelse ($groups as $heading => $items)
            <p class="px-2 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $heading }}</p>
            @foreach ($items as $item)
                <a href="{{ $item['url'] }}" wire:navigate @click="open = false" class="flex items-center justify-between gap-3 rounded-lg px-2 py-1.5 text-sm hover:bg-forest-50">
                    <span class="truncate font-medium text-zinc-900">{{ $item['label'] }}</span>
                    <span class="shrink-0 text-xs text-zinc-500">{{ $item['hint'] }}</span>
                </a>
            @endforeach
        @empty
            <p class="px-3 py-4 text-center text-sm text-zinc-500">No matches for “{{ $term }}”.</p>
        @endforelse
    </div>
</div>
