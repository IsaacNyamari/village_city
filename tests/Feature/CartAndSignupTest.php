<?php

namespace Tests\Feature;

use App\Livewire\Pages\Farmers;
use App\Livewire\Pages\Marketplace;
use App\Livewire\Pages\OrderShow;
use App\Livewire\Site\Catalogue;
use App\Livewire\Site\FarmerSignup;
use App\Models\County;
use App\Models\Customer;
use App\Models\Farmer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use App\Support\Cart;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CartAndSignupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, ReferenceDataSeeder::class]);
    }

    public function test_guests_can_fill_a_cart_and_are_sent_to_log_in_at_checkout(): void
    {
        $kale = Product::where('name', 'Kale')->first();

        Livewire::test(Catalogue::class)->call('add', $kale->id)->call('add', $kale->id)->assertDispatched('cart-updated');

        $this->assertEquals([$kale->id => (float) $kale->min_order_qty + 1], Cart::all());

        Livewire::test(Catalogue::class)->call('checkout')->assertRedirect(route('login'));
        $this->assertSame(route('marketplace', ['cart' => 1]), session('url.intended'));
    }

    public function test_the_cart_survives_login_and_shows_in_the_marketplace(): void
    {
        $kale = Product::where('name', 'Kale')->first();
        Cart::add($kale);
        $user = User::factory()->create()->assignRole('Customer');
        Customer::create(['user_id' => $user->id, 'name' => 'Buyer', 'status' => 'active']);

        $this->actingAs($user);

        Livewire::test(Marketplace::class)->assertSet('cart', [$kale->id => (float) $kale->min_order_qty]);
    }

    public function test_the_stepper_changes_quantity_and_removes_below_the_minimum(): void
    {
        $user = User::factory()->create()->assignRole('Customer');
        $this->actingAs($user);
        $kale = Product::where('name', 'Kale')->first();
        $kale->update(['min_order_qty' => 5]);

        $test = Livewire::test(Marketplace::class)->call('add', $kale->id)->assertSet('cart', [$kale->id => 5.0]);
        $test->call('step', $kale->id, 1)->assertSet('cart', [$kale->id => 6.0]);
        $test->call('step', $kale->id, -1)->call('step', $kale->id, -1)->assertSet('cart', []);
        $this->assertSame([], Cart::all());
    }

    public function test_inactive_products_cannot_be_added_to_the_cart(): void
    {
        $rice = Product::where('name', 'Rice')->first();
        $rice->update(['status' => 'inactive']);

        try {
            Livewire::test(Catalogue::class)->call('add', $rice->id);
        } catch (\Throwable) {
            // Livewire may surface the 404 as an exception; either way nothing is added.
        }

        $this->assertSame([], Cart::all());
    }

    public function test_reorder_puts_the_orders_items_back_in_the_cart(): void
    {
        $user = User::factory()->create()->assignRole('Customer');
        $customer = Customer::create(['user_id' => $user->id, 'name' => 'Buyer', 'status' => 'active']);
        $kale = Product::where('name', 'Kale')->first();
        $order = app(OrderService::class)->place($customer, [['product_id' => $kale->id, 'quantity' => 40]], [], 'marketplace');

        $this->actingAs($user);
        Livewire::test(OrderShow::class, ['order' => $order])->call('reorder')->assertRedirect(route('marketplace', ['cart' => 1]));

        $this->assertEquals([$kale->id => 40.0], Cart::all());
    }

    public function test_website_farmer_registration_creates_a_pending_farmer_for_review(): void
    {
        $admin = User::factory()->create()->assignRole(User::SUPER_ADMIN);
        $county = County::where('name', 'Kakamega')->first();
        $maize = Product::where('name', 'Maize')->first();

        Livewire::test(FarmerSignup::class)
            ->set('name', 'Wafula Barasa')->set('phone', '0733000999')->set('county_id', (string) $county->id)->set('product_ids', [(string) $maize->id])
            ->call('submit')->assertHasNoErrors()->assertSet('sent', true);

        $farmer = Farmer::where('phone', '0733000999')->firstOrFail();
        $this->assertSame('pending', $farmer->status);
        $this->assertCount(1, $farmer->products);
        $this->assertSame(1, $admin->notifications()->where('data', 'like', '%farmer registration%')->count());

        // Duplicate phone numbers do not create a second record.
        Livewire::test(FarmerSignup::class)->set('name', 'Wafula B')->set('phone', '0733000999')->set('county_id', (string) $county->id)->set('product_ids', [(string) $maize->id])->call('submit');
        $this->assertSame(1, Farmer::where('phone', '0733000999')->count());

        // Pending farmers cannot be used in procurement until approved.
        $this->actingAs($admin);
        Livewire::test(Farmers::class)->call('approve', $farmer->id);
        $this->assertSame('active', $farmer->refresh()->status);
    }

    public function test_farmer_registration_requires_core_details_and_ignores_bots(): void
    {
        Livewire::test(FarmerSignup::class)->call('submit')->assertHasErrors(['name', 'phone', 'county_id', 'product_ids']);

        $county = County::first();
        Livewire::test(FarmerSignup::class)->set('name', 'Bot')->set('phone', '0700000000')->set('county_id', (string) $county->id)
            ->set('product_ids', [(string) Product::first()->id])->set('website', 'spam')->call('submit');
        $this->assertSame(0, Farmer::count());
    }

    public function test_catalogue_photos_resolve_for_seeded_commodities(): void
    {
        $this->assertNotNull(Product::where('name', 'Tomatoes')->first()->imageUrl());
        $this->assertNotNull(Product::where('name', 'Spinach')->first()->imageUrl());
        $this->get('/sell-with-us')->assertOk()->assertSee('Sell your harvest');
        $this->get('/produce')->assertOk()->assertSee('Add to cart');
    }
}
