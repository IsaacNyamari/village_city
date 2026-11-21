<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Reference data and roles are always seeded. The development administrator and demo
     * business data are only created outside production.
     */
    public function run(): void
    {
        $this->call([RolesAndPermissionsSeeder::class, ReferenceDataSeeder::class, FaqSeeder::class]);

        if (app()->environment('production')) {
            return;
        }

        $admin = User::firstOrCreate(['email' => 'admin@villagecity.test'], [
            'name' => 'System Administrator',
            'password' => 'password',
            'phone' => '0700000000',
            'is_active' => true,
        ]);
        $admin->syncRoles([User::SUPER_ADMIN]);

        $this->call(DemoDataSeeder::class);
    }
}
