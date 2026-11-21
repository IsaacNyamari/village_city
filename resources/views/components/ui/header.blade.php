@props(['title', 'description' => null, 'back' => null, 'image' => null])

@if ($image)
    {{-- Photo banner header --}}
    <div class="relative overflow-hidden rounded-2xl bg-forest-900 text-white">
        <img src="{{ asset('images/'.$image.'.jpg') }}" alt="" class="absolute inset-0 size-full object-cover opacity-45" loading="lazy">
        <div class="absolute inset-0 bg-gradient-to-r from-forest-950/90 via-forest-900/70 to-forest-900/15"></div>
        <div class="relative flex flex-wrap items-end justify-between gap-4 px-6 py-7 sm:py-9">
            <div class="min-w-0">
                @if ($back)
                    <a href="{{ $back }}" wire:navigate class="mb-1 inline-flex items-center gap-1 text-xs font-medium text-forest-200 hover:text-white">
                        <flux:icon.arrow-left variant="micro" /> Back
                    </a>
                @endif
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ $title }}</h1>
                @if ($description)
                    <p class="mt-1 max-w-3xl text-sm text-forest-100">{{ $description }}</p>
                @endif
            </div>
            @if (trim($slot))
                <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
            @endif
        </div>
    </div>
@else
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            @if ($back)
                <a href="{{ $back }}" wire:navigate class="mb-1 inline-flex items-center gap-1 text-xs font-medium text-zinc-500 hover:text-forest-700">
                    <flux:icon.arrow-left variant="micro" /> Back
                </a>
            @endif
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900">{{ $title }}</h1>
            @if ($description)
                <p class="mt-1 max-w-3xl text-sm text-zinc-500">{{ $description }}</p>
            @endif
        </div>
        @if (trim($slot))
            <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
        @endif
    </div>
@endif
