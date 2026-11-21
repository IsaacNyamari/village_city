@props(['title' => null, 'description' => null, 'padded' => false])

<section {{ $attributes->class('overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs') }}>
    @if ($title)
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-200 px-5 py-3.5">
            <div>
                <h2 class="text-sm font-semibold text-zinc-900">{{ $title }}</h2>
                @if ($description)
                    <p class="text-xs text-zinc-500">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif
    <div @class(['p-5' => $padded])>
        {{ $slot }}
    </div>
</section>
