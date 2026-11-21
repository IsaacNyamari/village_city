<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Docs;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HelpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    public function test_guests_cannot_open_the_help_pages(): void
    {
        $this->get('/help')->assertRedirect('/login');
        $this->get('/help/roles')->assertRedirect('/login');
    }

    public function test_every_role_can_open_help_and_sees_its_own_quick_start(): void
    {
        foreach (Role::pluck('name') as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get('/help')->assertOk()
                ->assertSee('Your quick start')
                ->assertSee('Your quick start — '.e($role), false);
        }
    }

    public function test_every_role_has_a_section_in_the_roles_guide(): void
    {
        $guide = file_get_contents(base_path('docs/roles.md'));

        foreach (Role::pluck('name') as $role) {
            $this->assertStringContainsString("\n## {$role}\n", $guide, "docs/roles.md has no section for {$role}");
        }
    }

    public function test_a_user_without_a_role_can_still_read_the_help_landing_page(): void
    {
        $this->actingAs(User::factory()->create())->get('/help')->assertOk()->assertSee('No role is assigned');
    }

    public function test_role_specific_content_is_shown(): void
    {
        $this->actingAs($this->userWithRole('Quality Control Officer'))->get('/help')->assertOk()->assertSee('Awaiting inspection');
        $this->actingAs($this->userWithRole('Finance Manager'))->get('/help')->assertOk()->assertSee('Supplier Settlements');
        $this->actingAs($this->userWithRole('Farmer'))->get('/help')->assertOk()->assertSee('you are paid for the accepted quantity', false);
    }

    public function test_guides_are_limited_by_audience(): void
    {
        $customer = $this->userWithRole('Customer');
        $this->actingAs($customer)->get('/help/customer-guide')->assertOk()->assertSee('Check out');
        $this->get('/help/roles')->assertOk();
        $this->get('/help/user-guide')->assertForbidden();
        $this->get('/help/modules')->assertForbidden();
        $this->get('/help/setup')->assertForbidden();
        $this->get('/help/architecture')->assertForbidden();

        $staff = $this->userWithRole('Sales Manager');
        $this->actingAs($staff)->get('/help/user-guide')->assertOk()->assertSee('Workflow 3');
        $this->get('/help/modules')->assertOk();
        $this->get('/help/setup')->assertForbidden();

        $this->get('/help/seo-guide')->assertForbidden();

        $seoManager = $this->userWithRole('Content & SEO Manager');
        $this->actingAs($seoManager)->get('/help/seo-guide')->assertOk()->assertSee('Go-live checklist')->assertSee('Writing guidelines');
        $this->get('/help/setup')->assertForbidden();

        $admin = $this->userWithRole('Administrator');
        $this->actingAs($admin)->get('/help/seo-guide')->assertOk();
        $this->actingAs($admin)->get('/help/setup')->assertOk()->assertSee('Going to production');
        $this->get('/help/architecture')->assertOk()->assertSee('State machines');
    }

    public function test_unknown_guides_return_404(): void
    {
        $this->actingAs($this->userWithRole(User::SUPER_ADMIN))->get('/help/does-not-exist')->assertNotFound();
    }

    public function test_links_between_guides_point_at_the_in_app_pages_and_html_is_stripped(): void
    {
        $html = Docs::markdown("See [setup](setup.md#going-to-production) and [other](https://example.com).\n\n<script>alert(1)</script>\n\n[bad](javascript:alert(1))");

        $this->assertStringContainsString('href="'.route('help.show', 'setup').'#going-to-production"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);

        $this->actingAs($this->userWithRole(User::SUPER_ADMIN))->get('/help/user-guide')->assertOk()->assertSee(route('help.show', 'modules'), false);
    }

    public function test_search_only_returns_guides_the_user_may_read(): void
    {
        $customer = $this->userWithRole('Customer');
        $admin = $this->userWithRole('Administrator');

        $this->assertSame([], Docs::search('queue worker', $customer));
        $this->assertContains('setup', array_column(Docs::search('queue worker', $admin), 'slug'));
        $this->assertContains('customer-guide', array_column(Docs::search('Reorder', $customer), 'slug'));
        $this->assertSame([], Docs::search('x', $admin)); // too short
    }

    public function test_the_help_link_is_in_the_navigation_for_every_role(): void
    {
        foreach (['Customer', 'Farmer', 'Quality Control Officer', 'CEO'] as $role) {
            $this->actingAs($this->userWithRole($role))->get('/help')->assertSee('Help &amp; Documentation', false);
        }
    }

    public function test_users_with_no_operational_pages_are_sent_to_help_from_the_dashboard(): void
    {
        $this->actingAs($this->userWithRole('Farmer'))->get('/dashboard')->assertRedirect(route('help.index'));
    }

    public function test_every_guide_file_renders_with_a_table_of_contents(): void
    {
        foreach (array_keys(Docs::registry()) as $slug) {
            $page = Docs::render($slug);
            $this->assertNotEmpty($page['html'], $slug);
            $this->assertStringNotContainsString('not available in this installation', $page['html'], $slug);
            $this->assertNotEmpty($page['toc'], "{$slug} has no headings with ids");
        }
    }
}
