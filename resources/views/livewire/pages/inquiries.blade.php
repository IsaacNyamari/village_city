<div class="space-y-6">
    <x-ui.header title="Website Inquiries" description="Messages sent through the public website contact form.">
        <flux:badge color="{{ $openCount ? 'amber' : 'zinc' }}">{{ $openCount }} open</flux:badge>
    </x-ui.header>

    <x-ui.panel>
        <div class="flex gap-2 border-b border-zinc-200 p-4">
            @foreach (['open' => 'Open', 'handled' => 'Handled', 'all' => 'All'] as $k => $l)
                <button type="button" wire:click="$set('filter','{{ $k }}')" @class(['rounded-full border px-3 py-1.5 text-sm font-medium', 'border-forest-700 bg-forest-700 text-white' => $filter === $k, 'border-zinc-200 text-zinc-700' => $filter !== $k])>{{ $l }}</button>
            @endforeach
        </div>
        @forelse ($rows as $m)
            <div class="border-b border-zinc-100 px-5 py-4 last:border-0" wire:key="m-{{ $m->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-medium text-zinc-900">{{ $m->name }}{{ $m->organisation ? ' · '.$m->organisation : '' }}</p>
                        <p class="text-xs text-zinc-500">
                            {{ $m->email }}{{ $m->email && $m->phone ? ' · ' : '' }}{{ $m->phone }} · {{ $topics[$m->topic] ?? $m->topic }} · {{ $m->created_at->diffForHumans() }}
                        </p>
                    </div>
                    <flux:button size="sm" :variant="$m->handled_at ? 'ghost' : 'primary'" wire:click="toggleHandled({{ $m->id }})">{{ $m->handled_at ? 'Reopen' : 'Mark handled' }}</flux:button>
                </div>
                <p class="mt-2 whitespace-pre-line text-sm text-zinc-700">{{ $m->message }}</p>
            </div>
        @empty
            <x-ui.empty icon="envelope" title="No inquiries" text="Messages from the website contact form will appear here." />
        @endforelse
        <div class="px-4 pb-3">{{ $rows->links() }}</div>
    </x-ui.panel>
</div>
