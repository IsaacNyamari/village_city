<div>
    <button type="button" wire:click="open" data-cart-target aria-label="Cart, {{ $count }} item(s)"
        class="relative inline-flex items-center gap-2 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm font-medium text-zinc-800 transition hover:border-forest-300 hover:bg-forest-50">
        <flux:icon.shopping-cart variant="mini" class="size-4 text-forest-700" />
        <span class="max-sm:hidden">Cart</span>
        <span wire:key="count-{{ $count }}" @class(['vc-pop flex min-w-5 items-center justify-center rounded-full px-1.5 text-xs font-semibold leading-5', 'bg-gold-400 text-forest-950' => $count > 0, 'bg-zinc-100 text-zinc-500' => $count === 0])>{{ $count }}</span>
    </button>
</div>
