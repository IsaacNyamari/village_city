<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
        $this->seed(RolesAndPermissionsSeeder::class);

        Route::middleware('web')->group(function () {
            Route::get('/_abort/{code}', fn (int $code) => abort($code));
            Route::get('/_abort-429', fn () => abort(429, '', ['Retry-After' => 90]));
            Route::get('/_boom', fn () => throw new \RuntimeException('Secret database password is hunter2'));
        });
    }

    public static function codes(): array
    {
        return [
            '401' => [401, 'Please sign in to continue'],
            '403' => [403, 'have access to this page'],
            '404' => [404, 'find that page'],
            '419' => [419, 'session has expired'],
            '429' => [429, 'Too many requests'],
            '500' => [500, 'Something went wrong on our side'],
            '503' => [503, 'be right back'],
        ];
    }

    /** @dataProvider codes */
    public function test_each_error_code_renders_the_branded_page(int $code, string $text): void
    {
        $response = $this->get("/_abort/{$code}");

        $response->assertStatus($code)->assertSee($text, false)->assertSee('VILLAGE CITY')->assertSee("Error {$code}", false);
        // Not the stock Laravel template.
        $response->assertDontSee('Illuminate')->assertDontSee('laravel.com', false);
    }

    public function test_unknown_urls_show_the_404_page_with_helpful_links(): void
    {
        $this->get('/no/such/page')->assertNotFound()->assertSee('find that page', false)->assertSee('Browse our produce')->assertSee('Home page');
    }

    public function test_a_forbidden_page_names_the_users_role_and_links_to_help(): void
    {
        $user = User::factory()->create()->assignRole('Quality Control Officer');

        $this->actingAs($user)->get('/users')->assertForbidden()
            ->assertSee('Quality Control Officer')->assertSee('Help &amp; Documentation', false)->assertSee('Go to my dashboard');
    }

    public function test_guests_see_guest_actions_not_dashboard_links(): void
    {
        $this->get('/_abort/403')->assertForbidden()->assertSee('Back to home')->assertDontSee('Go to my dashboard');
    }

    public function test_the_rate_limit_page_shows_the_wait_time(): void
    {
        $this->get('/_abort-429')->assertStatus(429)->assertSee('2 minute(s)', false);
    }

    public function test_server_errors_never_leak_exception_details(): void
    {
        $this->get('/_boom')->assertStatus(500)->assertSee('Something went wrong on our side')->assertDontSee('hunter2')->assertDontSee('RuntimeException');
    }

    public function test_the_401_page_offers_login_and_registration(): void
    {
        $this->get('/_abort/401')->assertStatus(401)->assertSee(route('login'), false)->assertSee(route('register'), false);
    }

    public function test_json_requests_still_get_json_errors(): void
    {
        $this->getJson('/no/such/page')->assertNotFound()->assertJsonStructure(['message']);
    }
}
