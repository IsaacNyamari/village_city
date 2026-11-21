@props(['label', 'value', 'icon' => 'chart-bar', 'hint' => null, 'tone' => 'forest', 'href' => null])

@php
    $tones = [
        'forest' => 'bg-forest-50 text-forest-700',
        'gold' => 'bg-gold-100 text-gold-600',
        'blue' => 'bg-sky-50 text-sky-700',
        'red' => 'bg-red-50 text-red-700',
        'zinc' => 'bg-zinc-100 text-zinc-600',
    ];
@endphp

<{{ $href ? 'a' : 'div' }} @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->class(['rounded-xl border border-zinc-200 bg-white p-4 shadow-xs', 'transition hover:border-forest-300' => $href]) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $label }}</p>
        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg {{ $tones[$tone] ?? $tones['forest'] }}">
            <flux:icon :name="$icon" variant="mini" class="size-4" />
        </span>
    </div>
    <p class="mt-2 truncate text-xl font-semibold tabular-nums text-zinc-900" title="{{ $value }}">{{ $value }}</p>
    @if ($hint)
        <p class="mt-0.5 truncate text-xs text-zinc-500">{{ $hint }}</p>
    @endif
</{{ $href ? 'a' : 'div' }}>
