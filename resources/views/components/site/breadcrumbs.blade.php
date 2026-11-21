@props(['items'])

<nav aria-label="Breadcrumb" class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
    <ol class="flex flex-wrap items-center gap-1.5 text-sm text-zinc-500">
        @foreach ($items as $i => [$label, $url])
            <li class="flex items-center gap-1.5">
                @if ($i > 0)<flux:icon.chevron-right variant="micro" class="size-3 text-zinc-400" />@endif
                @if ($url && ! $loop->last)
                    <a href="{{ $url }}" wire:navigate class="hover:text-forest-700 hover:underline">{{ $label }}</a>
                @else
                    <span class="font-medium text-zinc-700" @if ($loop->last) aria-current="page" @endif>{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
