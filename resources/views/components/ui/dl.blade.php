@props(['items' => [], 'cols' => 'sm:grid-cols-2 lg:grid-cols-3'])

<dl class="grid gap-x-6 gap-y-4 {{ $cols }}">
    @foreach ($items as $label => $value)
        <div class="min-w-0">
            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $label }}</dt>
            <dd class="mt-0.5 break-words text-sm text-zinc-900">{{ filled($value) ? $value : '—' }}</dd>
        </div>
    @endforeach
</dl>
