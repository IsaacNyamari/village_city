<?php

namespace Tests\Feature;

use App\Livewire\Site\Catalogue;
use App\Livewire\Site\Contact;
use App\Models\ContactMessage;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, ReferenceDataSeeder::class]);
    }

    public function test_public_pages_are_visible_to_guests(): void
    {
        $this->get('/')->assertOk()->assertSee('From the farm to the city')->assertSee('Kakamega');
        $this->get('/about')->assertOk()->assertSee('Our mission');
        $this->get('/produce')->assertOk()->assertSee('Avocados');
        $this->get('/contact')->assertOk()->assertSee('Contact us');
    }

    public function test_logged_in_users_also_see_the_website(): void
    {
        $this->actingAs(User::factory()->create()->assignRole(User::SUPER_ADMIN))->get('/')->assertOk()->assertSee('Dashboard');
    }

    public function test_the_public_catalogue_filters_and_hides_internal_data(): void
    {
        $tomatoes = Product::where('name', 'Tomatoes')->first();
        $tomatoes->update(['buying_price' => 12345.67, 'min_stock_level' => 98765]);

        Livewire::test(Catalogue::class)->set('search', 'Tomatoes')->assertSee('Tomatoes')->assertDontSee('Maize')
            ->assertDontSee('12,345.67')->assertDontSee('98765');

        Product::where('name', 'Rice')->update(['status' => 'inactive']);
        Livewire::test(Catalogue::class)->set('search', 'Rice')->assertDontSee('KES');
    }

    public function test_the_contact_form_stores_a_message_and_notifies_staff(): void
    {
        $admin = User::factory()->create()->assignRole(User::SUPER_ADMIN);

        Livewire::test(Contact::class)
            ->set('name', 'Wanjiku')->set('email', 'w@example.com')->set('topic', 'wholesale')->set('message', 'We need 2 tonnes of potatoes weekly.')
            ->call('send')->assertHasNoErrors()->assertSet('sent', true);

        $this->assertDatabaseHas('contact_messages', ['name' => 'Wanjiku', 'topic' => 'wholesale']);
        $this->assertSame(1, $admin->notifications()->where('data', 'like', '%website enquiry%')->count());
    }

    public function test_the_contact_form_is_validated_and_ignores_bots(): void
    {
        Livewire::test(Contact::class)->set('name', '')->set('message', 'short')->call('send')->assertHasErrors(['name', 'email', 'phone', 'message']);

        Livewire::test(Contact::class)->set('name', 'Bot')->set('email', 'b@example.com')->set('message', 'Buy cheap stuff now please')->set('website', 'http://spam.test')->call('send');
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_inquiries_inbox_is_restricted_to_permitted_roles(): void
    {
        ContactMessage::create(['name' => 'A', 'email' => 'a@example.com', 'message' => 'Hello there friend']);

        $this->actingAs(User::factory()->create()->assignRole('Quality Control Officer'))->get('/inquiries')->assertForbidden();
        $this->actingAs(User::factory()->create()->assignRole('Sales Manager'))->get('/inquiries')->assertOk()->assertSee('Hello there friend');
    }
}
