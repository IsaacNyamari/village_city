<?php

namespace Tests\Feature;

use App\Livewire\Pages\Farmers;
use App\Livewire\Pages\Marketplace;
use App\Livewire\Pages\Reports;
use App\Livewire\Pages\Users;
use App\Models\Customer;
use App\Models\Farmer;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InteractiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, ReferenceDataSeeder::class]);
    }

    public function test_a_farmer_can_be_registered_with_validation(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('Procurement Officer'));

        Livewire::test(Farmers::class)
            ->call('create')
            ->set('form.name', '')
            ->call('save')
            ->assertHasErrors(['form.name', 'form.phone', 'form.county_id']);

        Livewire::test(Farmers::class)
            ->call('create')
            ->set('form.name', 'Jane Wafula')
            ->set('form.phone', '0712345678')
            ->set('form.county_id', \App\Models\County::first()->id)
            ->set('form.product_ids', [(string) Product::first()->id])
            ->call('save')
            ->assertHasNoErrors();

        $farmer = Farmer::where('name', 'Jane Wafula')->firstOrFail();
        $this->assertMatchesRegularExpression('/^FRM-\d{5}$/', $farmer->number);
        $this->assertCount(1, $farmer->products);
    }

    public function test_users_without_manage_permission_cannot_create_records(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('Quality Control Officer'));
        Livewire::test(Farmers::class)->assertForbidden();

        $this->actingAs(User::factory()->create()->assignRole('CEO'));
        Livewire::test(Farmers::class)->assertOk()->call('create')->assertForbidden();
    }

    public function test_only_super_administrators_can_assign_the_super_administrator_role(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('Administrator'));

        Livewire::test(Users::class)
            ->call('create')
            ->set('form.name', 'Eve')->set('form.email', 'eve@example.com')->set('form.role', User::SUPER_ADMIN)->set('form.password', 'Str0ng-Passw0rd!')
            ->call('save')
            ->assertHasErrors(['form.role']);

        $this->assertDatabaseMissing('users', ['email' => 'eve@example.com']);
    }

    public function test_marketplace_checkout_places_an_order_for_the_logged_in_customer(): void
    {
        $user = User::factory()->create()->assignRole('Customer');
        $customer = Customer::create(['user_id' => $user->id, 'name' => $user->name, 'status' => 'active', 'payment_terms' => 'cash_on_delivery']);
        $product = Product::where('name', 'Kale')->first();
        $this->actingAs($user);

        Livewire::test(Marketplace::class)
            ->call('add', $product->id)
            ->set('checkout.delivery_address', 'Kilimani, Nairobi')
            ->call('placeOrder')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('orders', ['customer_id' => $customer->id, 'source' => 'marketplace', 'status' => 'pending_confirmation']);
    }

    public function test_customers_cannot_view_other_customers_orders(): void
    {
        $mine = User::factory()->create()->assignRole('Customer');
        Customer::create(['user_id' => $mine->id, 'name' => 'Me', 'status' => 'active']);
        $other = Customer::create(['name' => 'Someone else', 'status' => 'active']);
        $order = \App\Models\Order::create(['customer_id' => $other->id, 'status' => 'pending_confirmation']);

        $this->actingAs($mine)->get(route('orders.show', $order))->assertForbidden();
    }

    public function test_reports_render_and_export_csv(): void
    {
        $this->actingAs(User::factory()->create()->assignRole(User::SUPER_ADMIN));

        foreach (\App\Services\ReportBuilder::REPORTS as $key => $label) {
            Livewire::test(Reports::class)->set('report', $key)->assertOk();
        }

        Livewire::test(Reports::class)->set('report', 'financial')->call('export')->assertFileDownloaded();
    }

    public function test_registration_creates_a_marketplace_customer_account(): void
    {
        $response = \Livewire\Volt\Volt::test('auth.register')
            ->set('name', 'New Buyer')->set('email', 'buyer@example.com')->set('phone', '0722000111')
            ->set('customer_type', 'restaurant')->set('business_name', 'Mama Njeri Eatery')
            ->set('password', 'Str0ng-Passw0rd!')->set('password_confirmation', 'Str0ng-Passw0rd!')
            ->call('register');

        $response->assertHasNoErrors();
        $user = User::where('email', 'buyer@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('Customer'));
        $this->assertDatabaseHas('customers', ['user_id' => $user->id, 'name' => 'Mama Njeri Eatery', 'type' => 'restaurant', 'phone' => '0722000111', 'contact_person' => 'New Buyer']);
    }
}
