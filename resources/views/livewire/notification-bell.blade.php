<div wire:poll.60s class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
    <flux:button variant="ghost" size="sm" icon="bell" class="relative" aria-label="Notifications" @click="open = !open">
        @if ($unread > 0)<span class="absolute -right-0.5 -top-0.5 flex min-w-4 items-center justify-center rounded-full bg-gold-500 px-1 text-[10px] font-bold leading-4 text-white">{{ $unread > 9 ? '9+' : $unread }}</span>@endif
    </flux:button>

    <div x-show="open" x-cloak x-transition.opacity class="absolute right-0 z-30 mt-2 w-96 max-w-[92vw] overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-lg">
        <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-2.5">
            <span class="text-sm font-semibold">Notifications</span>
            @if ($unread > 0)<button type="button" wire:click="markAllRead" class="text-xs text-forest-700 hover:underline">Mark all read</button>@endif
        </div>
        <div class="max-h-96 overflow-y-auto">
            @forelse ($items as $n)
                <button type="button" wire:click="open('{{ $n->id }}')" wire:key="n-{{ $n->id }}" class="flex w-full items-start gap-3 border-b border-zinc-100 px-4 py-2.5 text-left last:border-0 hover:bg-zinc-50 {{ $n->read_at ? '' : 'bg-forest-50/60' }}">
                    <flux:icon :name="$n->data['icon'] ?? 'bell'" variant="mini" class="mt-0.5 size-4 shrink-0 text-forest-600" />
                    <span class="min-w-0">
                        <span class="block truncate text-sm {{ $n->read_at ? '' : 'font-semibold' }}">{{ $n->data['title'] }}</span>
                        <span class="line-clamp-2 text-xs text-zinc-500">{{ $n->data['message'] }}</span>
                        <span class="text-[11px] text-zinc-400">{{ $n->created_at->diffForHumans() }}</span>
                    </span>
                </button>
            @empty
                <div class="px-4 py-8 text-center text-sm text-zinc-500">You are all caught up.</div>
            @endforelse
        </div>
    </div>
</div>
