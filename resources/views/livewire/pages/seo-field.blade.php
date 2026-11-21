{{-- Generic editor for one schema field. Expects: $model (wire:model path), $field (schema array), $fileKey, $reset (wire:click expression) --}}
@php
    use App\Support\Seo as SeoHelper;
    $type = $field['type'];
    $label = $field['label'];
    $help = $field['help'] ?? null;
@endphp

@if ($type === 'image')
    <flux:field>
        <flux:label>{{ $label }}</flux:label>
        @php
            $upload = $files[$fileKey] ?? null;
            $currentPath = data_get($this, $model);
            $preview = $upload ? $upload->temporaryUrl() : SeoHelper::mediaUrl($currentPath);
        @endphp
        <div class="flex flex-wrap items-start gap-4">
            <div class="flex h-24 w-44 items-center justify-center overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50">
                @if ($preview)<img src="{{ $preview }}" alt="" class="size-full object-cover">@else<span class="text-xs text-zinc-400">No image</span>@endif
            </div>
            <div class="space-y-2">
                <input type="file" accept="image/jpeg,image/png,image/webp" wire:model="files.{{ $fileKey }}"
                    class="block w-full max-w-xs text-sm text-zinc-600 file:mr-3 file:rounded-md file:border-0 file:bg-forest-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-forest-800" />
                <div wire:loading wire:target="files.{{ $fileKey }}" class="text-xs text-zinc-500">Uploading…</div>
                <button type="button" wire:click="{{ $reset }}" class="text-xs text-zinc-500 underline hover:text-red-600">Reset to default</button>
            </div>
        </div>
        @if ($help)<flux:description>{{ $help }}</flux:description>@endif
        <flux:error name="files.{{ $fileKey }}" />
    </flux:field>
@elseif ($type === 'switch')
    <flux:field variant="inline">
        <flux:switch wire:model="{{ $model }}" />
        <flux:label>{{ $label }}</flux:label>
        @if ($help)<flux:description>{{ $help }}</flux:description>@endif
    </flux:field>
@elseif (in_array($type, ['textarea', 'list', 'pairs']))
    <flux:textarea wire:model="{{ $model }}" :label="$label" :description="$help" rows="{{ $type === 'textarea' ? 3 : 5 }}" />
@else
    <flux:input wire:model="{{ $model }}" :label="$label" :description="$help" :type="$type === 'url' ? 'url' : 'text'" />
@endif

@if (! empty($ai))
    <div class="mt-1.5"><x-ui.ai-button :target="$ai" wire:click="{{ $ai }}">Improve with AI</x-ui.ai-button></div>
@endif
