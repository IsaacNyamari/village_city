@php use App\Support\Options; @endphp
<div class="space-y-6">
    <x-ui.header title="Audit Logs" description="A permanent record of logins, record changes, status changes, financial and stock activity, and permission changes." />
    <x-ui.panel>
        <div class="flex flex-wrap items-end gap-3 border-b border-zinc-200 p-4">
            <div class="w-full sm:w-64"><flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search description…" clearable /></div>
            <div class="w-full sm:w-48"><flux:select wire:model.live="action" aria-label="Action"><flux:select.option value="">Action: All</flux:select.option>@foreach ($actions as $a)<flux:select.option value="{{ $a }}">{{ ucwords(str_replace('_', ' ', $a)) }}</flux:select.option>@endforeach</flux:select></div>
            <div class="w-full sm:w-52"><flux:select wire:model.live="user" aria-label="User"><flux:select.option value="">User: All</flux:select.option>@foreach ($users as $id => $n)<flux:select.option value="{{ $id }}">{{ $n }}</flux:select.option>@endforeach</flux:select></div>
        </div>
        @if ($rows->isEmpty())
            <x-ui.empty icon="finger-print" title="No audit entries found" />
        @else
            <flux:table :paginate="$rows" class="px-4" pagination:class="px-4 pb-3">
                <flux:table.columns><flux:table.column>When</flux:table.column><flux:table.column>User</flux:table.column><flux:table.column>Action</flux:table.column><flux:table.column>Description</flux:table.column><flux:table.column>IP</flux:table.column></flux:table.columns>
                <flux:table.rows>
                    @foreach ($rows as $row)
                        <flux:table.row :key="$row->id">
                            <flux:table.cell>{{ $row->created_at->format('d M Y H:i:s') }}</flux:table.cell>
                            <flux:table.cell>{{ $row->user?->name ?? 'System' }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" inset="top bottom" :color="match(true) { str_contains($row->action, 'delet') => 'red', $row->action === 'created' => 'green', $row->action === 'login' => 'sky', default => 'zinc' }">{{ ucwords(str_replace('_', ' ', $row->action)) }}</flux:badge></flux:table.cell>
                            <flux:table.cell class="max-w-xl truncate" title="{{ $row->description }}">{{ $row->description }}</flux:table.cell>
                            <flux:table.cell>{{ $row->ip_address ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-ui.panel>
</div>
