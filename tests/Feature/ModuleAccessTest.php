<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, ReferenceDataSeeder::class]);
    }

    protected function superAdmin(): User
    {
        return User::factory()->create()->assignRole(User::SUPER_ADMIN);
    }

    public function test_every_navigation_page_renders_for_a_super_administrator(): void
    {
        $this->actingAs($admin = $this->superAdmin());
        $count = 0;

        foreach (config('villagecity.navigation') as $items) {
            foreach ($items as [$label, $route]) {
                $this->get(route($route))->assertOk();
                $count++;
            }
        }

        $this->assertGreaterThan(30, $count);
        $this->get(route('products.categories'))->assertOk();
        $this->get(route('settings.profile'))->assertOk();
    }

    public function test_navigation_only_lists_pages_a_role_can_open(): void
    {
        $user = User::factory()->create()->assignRole('Quality Control Officer');
        $this->actingAs($user);

        $this->get(route('quality.index'))->assertOk();
        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('settlements.index'))->assertForbidden();
        $this->get(route('roles.index'))->assertForbidden();
    }

    public function test_customers_can_use_the_marketplace_but_not_staff_pages(): void
    {
        $customerUser = User::factory()->create()->assignRole('Customer');
        $this->actingAs($customerUser);

        $this->get(route('marketplace'))->assertOk();
        $this->get(route('orders.index'))->assertOk();
        $this->get(route('farmers.index'))->assertForbidden();
        $this->get(route('inventory.index'))->assertForbidden();
        $this->get(route('dashboard'))->assertRedirect(route('marketplace'));
    }

    public function test_deactivated_users_are_logged_out(): void
    {
        $user = User::factory()->create(['is_active' => false])->assignRole(User::SUPER_ADMIN);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_reference_data_is_seeded(): void
    {
        $this->assertDatabaseCount('counties', 11);
        $this->assertDatabaseHas('counties', ['name' => 'Kakamega']);
        $this->assertDatabaseHas('product_categories', ['name' => 'Village City Fresh']);
        $this->assertDatabaseHas('products', ['name' => 'Tomatoes']);
        $this->assertDatabaseHas('roles', ['name' => 'Finance Manager']);
    }
}
