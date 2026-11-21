<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_staff_can_visit_the_dashboard(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create()->assignRole('Operations Manager');

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Executive dashboard');
    }

    public function test_user_without_a_role_is_sent_to_help_instead_of_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertRedirect(route('help.index'));
    }
}
