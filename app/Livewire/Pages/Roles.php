<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\Page;
use App\Models\User;
use App\Services\AuditLogger;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class Roles extends Component
{
    use Page;

    public ?int $editingId = null;

    public bool $showForm = false;

    public string $name = '';

    public array $selected = [];

    public function mount(): void
    {
        $this->guard('roles.view');
    }

    public function create(): void
    {
        $this->guard('roles.manage');
        $this->resetValidation();
        $this->editingId = null;
        $this->name = '';
        $this->selected = [];
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->guard('roles.manage');
        $this->resetValidation();
        $role = Role::findOrFail($id);
        $this->editingId = $role->id;
        $this->name = $role->name;
        $this->selected = $role->permissions->pluck('name')->all();
        $this->showForm = true;
    }

    public function toggleModule(string $module): void
    {
        $names = Permission::where('name', 'like', $module.'.%')->pluck('name')->all();
        $this->selected = count(array_diff($names, $this->selected)) === 0
            ? array_values(array_diff($this->selected, $names))
            : array_values(array_unique([...$this->selected, ...$names]));
    }

    public function save(): void
    {
        $this->guard('roles.manage');
        $role = $this->editingId ? Role::findOrFail($this->editingId) : null;

        $this->validate([
            'name' => ['required', 'string', 'max:100', 'unique:roles,name,'.($role?->id ?? 'NULL')],
            'selected' => ['array'],
            'selected.*' => ['exists:permissions,name'],
        ]);

        // The super administrator role always has full access and cannot be edited or renamed.
        if ($role?->name === User::SUPER_ADMIN) {
            $this->toast('The Super Administrator role always has full access and cannot be modified.', 'danger');

            return;
        }

        $role ??= Role::create(['name' => $this->name, 'guard_name' => 'web']);
        $role->update(['name' => $this->name]);
        $role->syncPermissions($this->selected);
        AuditLogger::log('permissions_changed', null, 'Permissions of role "'.$role->name.'" updated ('.count($this->selected).' granted)', ['permissions' => $this->selected]);

        $this->showForm = false;
        $this->toast('Role saved.');
    }

    public function delete(int $id): void
    {
        $this->guard('roles.manage');
        $role = Role::withCount('users')->findOrFail($id);

        if ($role->name === User::SUPER_ADMIN || $role->users_count > 0) {
            $this->toast($role->users_count > 0 ? 'This role is assigned to users. Reassign them first.' : 'The Super Administrator role cannot be deleted.', 'danger');

            return;
        }

        $role->delete();
        AuditLogger::log('deleted', null, 'Role "'.$role->name.'" deleted');
        $this->toast('Role deleted.');
    }

    public function render()
    {
        return $this->page('livewire.pages.roles', [
            'roles' => Role::withCount(['users', 'permissions'])->orderBy('name')->get(),
            'groups' => Permission::orderBy('name')->get()->groupBy(fn ($p) => explode('.', $p->name)[0]),
            'locked' => $this->editingId && Role::find($this->editingId)?->name === User::SUPER_ADMIN,
        ], 'Roles & Permissions');
    }
}
