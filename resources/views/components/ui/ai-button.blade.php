{{-- Gemini suggestion button: <x-ui.ai-button target="aiPage" wire:click="aiPage('home')">Suggest with AI</x-ui.ai-button> --}}
@props(['target'])

<flux:button type="button" size="xs" icon="sparkles" {{ $attributes }} wire:loading.attr="disabled" wire:target="{{ $target }}">
    <span wire:loading.remove wire:target="{{ $target }}">{{ $slot }}</span>
    <span wire:loading wire:target="{{ $target }}">Thinking…</span>
</flux:button>
