<?php

namespace App\Livewire\Pages;

use App\Livewire\Crud\ResourcePage;
use App\Models\County;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Password;
use RuntimeException;
use Spatie\Permission\Models\Role;

class Users extends ResourcePage
{
    protected function model(): string
    {
        return User::class;
    }

    protected function title(): string
    {
        return 'Users';
    }

    protected function module(): string
    {
        return 'users';
    }

    protected function description(): string
    {
        return 'Staff, customer and farmer accounts. A user can only reach the modules their role permits.';
    }

    protected function query(): Builder
    {
        return User::query()->with(['roles', 'county']);
    }

    protected function searchable(): array
    {
        return ['name', 'email', 'phone'];
    }

    protected function hasStatusToggle(): bool
    {
        return false;
    }

    /** Only super administrators may hand out the super administrator role. */
    protected function roleOptions(): array
    {
        return Role::orderBy('name')->pluck('name', 'name')
            ->reject(fn ($name) => $name === User::SUPER_ADMIN && ! auth()->user()->isSuperAdmin())->all();
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Name', 'value' => fn ($r) => $r->name],
            ['label' => 'Email', 'value' => fn ($r) => $r->email],
            ['label' => 'Phone', 'value' => fn ($r) => $r->phone],
            ['label' => 'Role', 'value' => fn ($r) => $r->roles->pluck('name')->implode(', ') ?: 'No role'],
            ['label' => 'County', 'value' => fn ($r) => $r->county?->name],
            ['label' => 'Last login', 'value' => fn ($r) => $r->last_login_at?->diffForHumans() ?? 'Never'],
            ['label' => 'Status', 'value' => fn ($r) => $r->is_active ? 'active' : 'inactive', 'badge' => true],
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            'role' => ['label' => 'Role', 'options' => Role::orderBy('name')->pluck('name', 'name')->all(),
                'apply' => fn (Builder $q, $v) => $q->whereHas('roles', fn ($r) => $r->where('name', $v))],
            'is_active' => ['label' => 'Status', 'options' => ['1' => 'Active', '0' => 'Inactive']],
        ];
    }

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Full name', 'required' => true, 'rules' => ['required', 'string', 'max:255']],
            'email' => ['label' => 'Email', 'type' => 'email', 'required' => true, 'rules' => fn ($record) => ['required', 'email', 'max:255', 'unique:users,email,'.($record?->id ?? 'NULL')]],
            'phone' => ['label' => 'Phone', 'rules' => ['nullable', 'string', 'max:30']],
            'role' => ['label' => 'Role', 'type' => 'select', 'required' => true, 'options' => $this->roleOptions(), 'rules' => ['required', 'in:'.implode(',', array_keys($this->roleOptions()))]],
            'county_id' => ['label' => 'County (optional)', 'type' => 'select', 'options' => County::orderBy('name')->pluck('name', 'id')->all(), 'rules' => ['nullable', 'exists:counties,id']],
            'password' => ['label' => 'Password', 'type' => 'password', 'help' => 'Leave blank to keep the current password when editing.', 'rules' => fn ($record) => [$record ? 'nullable' : 'required', Password::defaults()]],
            'is_active' => ['label' => 'Account active', 'type' => 'switch', 'default' => true, 'rules' => ['boolean']],
        ];
    }

    protected function fillExtra(Model $record): array
    {
        return ['role' => $record->roleName() ?? ''];
    }

    protected function beforeSave(array $data, ?Model $record): array
    {
        if ($record?->isSuperAdmin() && ! auth()->user()->isSuperAdmin()) {
            throw new RuntimeException('Only a super administrator can change this account.');
        }

        if ($record && $record->is(auth()->user()) && ! $data['is_active']) {
            throw new RuntimeException('You cannot deactivate your own account.');
        }

        unset($data['role']);

        return $data;
    }

    protected function persist(array $data, ?Model $record): Model
    {
        // beforeSave may throw; run it inside attempt() via persist so the message is shown.
        return parent::persist($data, $record);
    }

    protected function afterSave(Model $record): void
    {
        $role = $this->form['role'] ?? null;

        if ($role && $record->roleName() !== $role) {
            $record->syncRoles([$role]);
            AuditLogger::log('role_changed', $record, 'Role of '.$record->name.' set to '.$role);
        }
    }

    protected function rowActions(): array
    {
        return [
            ['label' => 'Deactivate', 'icon' => 'pause-circle', 'method' => 'setActive', 'show' => fn ($r) => $r->is_active && ! $r->is(auth()->user())],
            ['label' => 'Activate', 'icon' => 'play-circle', 'method' => 'setActive', 'show' => fn ($r) => ! $r->is_active],
        ];
    }

    public function setActive(int $id): void
    {
        $this->guard('users.manage');
        $user = User::findOrFail($id);

        if ($user->is(auth()->user()) || ($user->isSuperAdmin() && ! auth()->user()->isSuperAdmin())) {
            $this->toast('You cannot change the status of this account.', 'danger');

            return;
        }

        $user->update(['is_active' => ! $user->is_active]);
        $this->toast('Account '.($user->is_active ? 'activated.' : 'deactivated.'));
    }

    public function delete(): void
    {
        $user = User::find($this->deletingId);

        if ($user && ($user->is(auth()->user()) || ($user->isSuperAdmin() && ! auth()->user()->isSuperAdmin()))) {
            $this->toast('You cannot delete this account.', 'danger');
            $this->showDelete = false;

            return;
        }

        parent::delete();
    }
}
