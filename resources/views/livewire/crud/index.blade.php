<div class="space-y-6">
    <x-ui.header :title="$title" :description="$description" :image="$banner">
        @foreach ($headerLinks as [$linkLabel, $linkUrl, $linkIcon])
            <flux:button :href="$linkUrl" :icon="$linkIcon" wire:navigate>{{ $linkLabel }}</flux:button>
        @endforeach
        @if ($canManage)
            <flux:button variant="primary" icon="plus" wire:click="create">New {{ strtolower($singular) }}</flux:button>
        @endif
    </x-ui.header>

    <x-ui.panel>
        @if ($hasSearch || $filterDefinitions)
            <div class="flex flex-wrap items-end gap-3 border-b border-zinc-200 p-4">
                @if ($hasSearch)
                    <div class="w-full sm:w-64">
                        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search {{ strtolower($title) }}…" clearable />
                    </div>
                @endif
                @foreach ($filterDefinitions as $name => $filter)
                    <div class="w-full sm:w-48">
                        <flux:select wire:model.live="filters.{{ $name }}" aria-label="{{ $filter['label'] }}">
                            <flux:select.option value="">{{ $filter['label'] }}: All</flux:select.option>
                            @foreach ($filter['options'] as $value => $label)
                                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                @endforeach
                <div wire:loading.delay class="pb-2 text-xs text-zinc-500">Loading…</div>
            </div>
        @endif

        @if ($rows->isEmpty())
            <x-ui.empty icon="inbox" :title="'No '.strtolower($title).' found'"
                :text="$search !== '' || array_filter($filters) ? 'Try changing the search or filters.' : 'Records you add will appear here.'">
                @if ($canManage && $search === '' && ! array_filter($filters))
                    <flux:button size="sm" variant="primary" icon="plus" wire:click="create">Add the first {{ strtolower($singular) }}</flux:button>
                @endif
            </x-ui.empty>
        @else
            <flux:table :paginate="$rows" class="px-4" pagination:class="px-4 pb-3">
                <flux:table.columns>
                    @foreach ($columns as $column)
                        <flux:table.column>{{ $column['label'] }}</flux:table.column>
                    @endforeach
                    <flux:table.column align="end"></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($rows as $row)
                        <flux:table.row :key="$row->getKey()">
                            @foreach ($columns as $index => $column)
                                @php $value = $column['value']($row); @endphp
                                <flux:table.cell class="{{ $column['class'] ?? '' }}">
                                    @if (! empty($column['badge']))
                                        <x-ui.status :value="$value" />
                                    @elseif ($index === 0 && $showRoute)
                                        <a href="{{ route($showRoute, $row) }}" wire:navigate class="font-medium text-forest-700 hover:underline">{{ $value ?? '—' }}</a>
                                    @elseif ($index === 0)
                                        <span class="font-medium text-zinc-900">{{ $value ?? '—' }}</span>
                                    @else
                                        {{ filled($value) ? $value : '—' }}
                                    @endif
                                </flux:table.cell>
                            @endforeach
                            <flux:table.cell align="end">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" aria-label="Actions" />
                                    <flux:menu>
                                        @if ($showRoute)
                                            <flux:menu.item icon="eye" :href="route($showRoute, $row)" wire:navigate>View</flux:menu.item>
                                        @endif
                                        @if ($canManage)
                                            @if ($editable)<flux:menu.item icon="pencil-square" wire:click="edit({{ $row->getKey() }})">Edit</flux:menu.item>@endif
                                            @foreach ($rowActions as $action)
                                                @if (! isset($action['show']) || $action['show']($row))
                                                    @if (! empty($action['confirm']))
                                                        <flux:menu.item :icon="$action['icon']" wire:click="{{ $action['method'] }}({{ $row->getKey() }})" wire:confirm="{{ $action['confirm'] }}">{{ $action['label'] }}</flux:menu.item>
                                                    @else
                                                        <flux:menu.item :icon="$action['icon']" wire:click="{{ $action['method'] }}({{ $row->getKey() }})">{{ $action['label'] }}</flux:menu.item>
                                                    @endif
                                                @endif
                                            @endforeach
                                            @if ($statusToggle)
                                                <flux:menu.item :icon="$row->status === 'active' ? 'pause-circle' : 'play-circle'" wire:click="toggleStatus({{ $row->getKey() }})">
                                                    {{ $row->status === 'active' ? 'Deactivate' : 'Activate' }}
                                                </flux:menu.item>
                                            @endif
                                            @if ($deletable)
                                                <flux:menu.separator />
                                                <flux:menu.item variant="danger" icon="trash" wire:click="confirmDelete({{ $row->getKey() }})">Delete</flux:menu.item>
                                            @endif
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-ui.panel>

    <flux:modal wire:model.self="showForm" class="w-full max-w-3xl">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit' : 'New' }} {{ strtolower($singular) }}</flux:heading>
                <flux:subheading>Fields marked with * are required.</flux:subheading>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($fields as $name => $field)
                    @php
                        $type = $field['type'] ?? 'text';
                        $label = $field['label'].(! empty($field['required']) ? ' *' : '');
                        $wide = ! empty($field['wide']) || in_array($type, ['textarea', 'checkboxes']);
                    @endphp
                    <div class="{{ $wide ? 'sm:col-span-2' : '' }}" wire:key="field-{{ $name }}">
                        @if ($type === 'select')
                            <flux:select wire:model="form.{{ $name }}" :label="$label" :description="$field['help'] ?? null">
                                <flux:select.option value="">— Select —</flux:select.option>
                                @foreach ($field['options'] as $value => $optionLabel)
                                    <flux:select.option value="{{ $value }}">{{ $optionLabel }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        @elseif ($type === 'textarea')
                            <flux:textarea wire:model="form.{{ $name }}" :label="$label" rows="3" />
                        @elseif ($type === 'switch')
                            <flux:field variant="inline" class="pt-6">
                                <flux:label>{{ $label }}</flux:label>
                                <flux:switch wire:model="form.{{ $name }}" />
                            </flux:field>
                        @elseif ($type === 'checkboxes')
                            <flux:field>
                                <flux:label>{{ $label }}</flux:label>
                                @if (empty($field['options']))
                                    <flux:text class="text-sm">No options available yet.</flux:text>
                                @else
                                    <div class="grid max-h-44 grid-cols-2 gap-2 overflow-y-auto rounded-lg border border-zinc-200 p-3 sm:grid-cols-3">
                                        @foreach ($field['options'] as $value => $optionLabel)
                                            <flux:checkbox wire:model="form.{{ $name }}" value="{{ $value }}" :label="$optionLabel" />
                                        @endforeach
                                    </div>
                                @endif
                                <flux:error name="form.{{ $name }}" />
                            </flux:field>
                        @elseif ($type === 'image')
                            <flux:field>
                                <flux:label>{{ $label }}</flux:label>
                                <input type="file" wire:model="upload" accept="image/*"
                                    class="block w-full rounded-lg border border-zinc-200 text-sm text-zinc-600 file:mr-3 file:border-0 file:bg-forest-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-forest-800" />
                                <div wire:loading wire:target="upload" class="text-xs text-zinc-500">Uploading…</div>
                                <flux:error name="upload" />
                            </flux:field>
                        @else
                            <flux:input wire:model="form.{{ $name }}" :label="$label" :type="$type === 'datetime' ? 'datetime-local' : $type"
                                :step="$type === 'number' ? ($field['step'] ?? '0.01') : null" :min="$type === 'number' ? '0' : null"
                                :placeholder="$field['placeholder'] ?? null" :description="$field['help'] ?? null"
                                :viewable="$type === 'password'" :autocomplete="$type === 'password' ? 'new-password' : 'off'" />
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save,upload">Save {{ strtolower($singular) }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showDelete" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete this {{ strtolower($singular) }}?</flux:heading>
                <flux:subheading>This cannot be undone. Records with transaction history cannot be deleted — deactivate them instead.</flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showDelete', false)">Cancel</flux:button>
                <flux:button variant="danger" wire:click="delete">Delete</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
