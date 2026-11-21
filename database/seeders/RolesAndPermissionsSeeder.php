<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = [];
        $created = [];

        foreach (config('villagecity.permissions') as $module => $actions) {
            foreach ($actions as $action) {
                $permission = Permission::findOrCreate("$module.$action", 'web');
                $all[] = $permission->name;

                if ($permission->wasRecentlyCreated) {
                    $created[] = $permission->name;
                }
            }
        }

        $matches = fn (array $patterns, array $names) => collect($names)->filter(fn ($name) => collect($patterns)->contains(fn ($pattern) => Str::is($pattern, $name)))->all();

        foreach (config('villagecity.roles') as $name => $patterns) {
            $role = Role::findOrCreate($name, 'web');

            if ($role->wasRecentlyCreated) {
                $role->syncPermissions($matches($patterns, $all));
            } elseif ($created) {
                // Existing roles keep administrator customisations; they only gain newly introduced permissions.
                $role->givePermissionTo($matches($patterns, $created));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
