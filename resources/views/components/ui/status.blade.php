@props(['value' => null, 'list' => null])

@php
    if ($value instanceof \BackedEnum) {
        $label = $value->label();
        $color = $value->color();
    } else {
        $label = $list ? \App\Support\Options::label($list, $value) : (filled($value) ? ucwords(str_replace('_', ' ', (string) $value)) : '—');
        $label = strlen((string) $value) === 1 ? 'Grade '.$value : $label;
        $color = \App\Support\Options::color($value);
    }
@endphp

@if (filled($value))
    <flux:badge size="sm" :color="$color" inset="top bottom">{{ $label }}</flux:badge>
@else
    <span class="text-zinc-400">—</span>
@endif
