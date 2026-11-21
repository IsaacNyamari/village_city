@props(['icon' => 'inbox', 'title' => 'Nothing here yet', 'text' => null])

<div {{ $attributes->class('flex flex-col items-center justify-center px-6 py-12 text-center') }}>
    <span class="flex size-11 items-center justify-center rounded-full bg-forest-50 text-forest-600">
        <flux:icon :name="$icon" class="size-5" />
    </span>
    <p class="mt-3 text-sm font-medium text-zinc-900">{{ $title }}</p>
    @if ($text)
        <p class="mt-1 max-w-sm text-sm text-zinc-500">{{ $text }}</p>
    @endif
    @if (trim($slot))
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
