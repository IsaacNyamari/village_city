@php use App\Models\User; @endphp
<div class="space-y-6">
    <x-ui.header title="Roles & Permissions" description="Roles bundle permissions. Users can only open the modules their role allows. Permission names follow module.action.">
        @can('roles.manage')<flux:button variant="primary" icon="plus" wire:click="create">New role</flux:button>@endcan
    </x-ui.header>

    <x-ui.panel>
        <flux:table class="px-4">
            <flux:table.columns><flux:table.column>Role</flux:table.column><flux:table.column align="end">Users</flux:table.column><flux:table.column align="end">Permissions</flux:table.column><flux:table.column></flux:table.column></flux:table.columns>
            <flux:table.rows>
                @foreach ($roles as $role)
                    <flux:table.row :key="$role->id">
                        <flux:table.cell class="font-medium text-zinc-900">{{ $role->name }} @if ($role->name === User::SUPER_ADMIN)<flux:badge size="sm" color="amber" inset="top bottom">Full access</flux:badge>@endif</flux:table.cell>
                        <flux:table.cell align="end">{{ $role->users_count }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $role->name === User::SUPER_ADMIN ? 'All' : $role->permissions_count }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @can('roles.manage')
                                @if ($role->name !== User::SUPER_ADMIN)
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $role->id }})" aria-label="Edit" />
                                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $role->id }})" wire:confirm="Delete the role '{{ $role->name }}'?" aria-label="Delete" />
                                @endif
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </x-ui.panel>

    <flux:modal wire:model.self="showForm" class="w-full max-w-4xl">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? 'Edit role' : 'New role' }}</flux:heading>
            <flux:input wire:model="name" label="Role name *" />
            <div class="grid max-h-[50vh] gap-3 overflow-y-auto sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($groups as $module => $permissions)
                    <div class="rounded-lg border border-zinc-200 p-3" wire:key="grp-{{ $module }}">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-sm font-semibold capitalize">{{ str_replace('_', ' ', $module) }}</span>
                            <button type="button" class="text-xs text-forest-700 hover:underline" wire:click="toggleModule('{{ $module }}')">Toggle all</button>
                        </div>
                        <div class="space-y-1.5">
                            @foreach ($permissions as $permission)
                                <flux:checkbox wire:model="selected" value="{{ $permission->name }}" :label="ucfirst(explode('.', $permission->name)[1])" />
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-end gap-2"><flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">Cancel</flux:button><flux:button type="submit" variant="primary">Save role</flux:button></div>
        </form>
    </flux:modal>
</div>
