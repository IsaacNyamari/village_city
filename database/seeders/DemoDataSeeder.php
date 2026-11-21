<?php

namespace Database\Seeders;

use App\Models\CollectionCentre;
use App\Models\County;
use App\Models\Customer;
use App\Models\DeliveryRoute;
use App\Models\Farmer;
use App\Models\Procurement;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\FinanceService;
use App\Services\LogisticsService;
use App\Services\OrderService;
use App\Services\ProcurementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Development-only demo data. Everything is created through the real services so stock,
 * invoices, ledger entries and audit logs are consistent. Never run in production.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (Farmer::exists()) {
            return; // already seeded
        }

        Auth::login(User::where('email', 'admin@villagecity.test')->firstOrFail());

        foreach (['Operations Manager' => 'ops', 'Procurement Officer' => 'procurement', 'Finance Manager' => 'finance', 'Sales Manager' => 'sales', 'Logistics Manager' => 'logistics', 'Quality Control Officer' => 'quality', 'Warehouse Manager' => 'warehouse'] as $role => $slug) {
            User::firstOrCreate(['email' => "$slug@villagecity.test"], ['name' => $role.' (demo)', 'password' => 'password', 'is_active' => true])->syncRoles([$role]);
        }

        $county = fn (string $name) => County::where('name', $name)->value('id');

        $kakamega = CollectionCentre::create(['name' => 'Kakamega Aggregation Centre', 'county_id' => $county('Kakamega'), 'location' => 'Mumias Road, Kakamega', 'capacity_kg' => 50000, 'contact_phone' => '0711000001', 'manager_id' => User::where('email', 'warehouse@villagecity.test')->value('id')]);
        $bungoma = CollectionCentre::create(['name' => 'Bungoma Collection Centre', 'county_id' => $county('Bungoma'), 'location' => 'Webuye Road, Bungoma', 'capacity_kg' => 30000, 'contact_phone' => '0711000002']);
        $nairobi = CollectionCentre::create(['name' => 'Nairobi Distribution Hub', 'type' => 'warehouse', 'location' => 'Industrial Area, Nairobi', 'capacity_kg' => 100000, 'contact_phone' => '0711000003']);

        $coop = Supplier::create(['business_name' => 'Lurambi Farmers Cooperative', 'type' => 'cooperative', 'contact_person' => 'Grace Wanjala', 'phone' => '0722000001', 'county_id' => $county('Kakamega'), 'payment_terms' => 'net_7']);
        $commercial = Supplier::create(['business_name' => 'Kitale Grain Traders', 'type' => 'commercial', 'contact_person' => 'Peter Kiprop', 'phone' => '0722000002', 'county_id' => $county('Trans Nzoia'), 'payment_terms' => 'cash_on_delivery']);
        $commercial->products()->sync(Product::whereIn('name', ['Maize', 'Beans', 'Wheat'])->pluck('id'));

        $farmers = collect([
            ['Mary Atieno', 'Kisumu', $kakamega, ['Tomatoes', 'Onions', 'Kale']], ['John Wekesa', 'Bungoma', $bungoma, ['Maize', 'Beans', 'Sorghum']],
            ['Esther Nafula', 'Kakamega', $kakamega, ['Cabbages', 'Carrots', 'Spinach']], ['Samuel Omondi', 'Siaya', $kakamega, ['Tomatoes', 'Capsicum']],
            ['Alice Moraa', 'Kisii', $kakamega, ['Bananas', 'Avocados', 'Potatoes']], ['Joseph Barasa', 'Busia', $bungoma, ['Groundnuts', 'Cowpeas', 'Millet']],
        ])->map(function ($row, $i) use ($county, $coop) {
            [$name, $countyName, $centre, $products] = $row;
            $farmer = Farmer::create(['name' => $name, 'phone' => '07330000'.str_pad($i + 1, 2, '0', STR_PAD_LEFT), 'county_id' => $county($countyName), 'village' => 'Demo village', 'farm_size_acres' => 2 + $i, 'collection_centre_id' => $centre->id, 'cooperative_id' => $i < 3 ? $coop->id : null, 'registered_at' => now()->subDays(40 - $i * 5), 'status' => 'active']);
            $farmer->products()->sync(Product::whereIn('name', $products)->pluck('id'));

            return $farmer;
        });

        $customers = collect([
            ['Naivas Supermarket – Westlands', 'supermarket', 'net_14', 500000], ['Serena Hotel Nairobi', 'hotel', 'net_30', 800000], ['Alliance Girls High School', 'school', 'net_30', 300000],
            ['Mama Pima Restaurant', 'restaurant', 'cash_on_delivery', 0], ['Gikomba Wholesalers', 'wholesale', 'prepaid', 0],
        ])->map(fn ($c, $i) => Customer::create(['status' => 'active', 'name' => $c[0], 'type' => $c[1], 'payment_terms' => $c[2], 'credit_limit' => $c[3], 'phone' => '0744000'.str_pad($i + 1, 3, '0', STR_PAD_LEFT), 'county' => 'Nairobi', 'delivery_address' => 'Nairobi, Kenya', 'billing_address' => 'Nairobi, Kenya', 'contact_person' => 'Procurement Office']));

        $transporter = Transporter::create(['name' => 'Western Haulers Ltd', 'contact_person' => 'David Otieno', 'phone' => '0755000001', 'location' => 'Kakamega']);
        $vehicle = Vehicle::create(['registration' => 'KDA 123A', 'type' => 'canter', 'capacity_kg' => 4000, 'transporter_id' => $transporter->id, 'driver_name' => 'Hassan Mwangi', 'driver_phone' => '0755000002', 'insurance_expiry' => now()->addMonths(8), 'inspection_expiry' => now()->addMonths(5)]);
        Vehicle::create(['registration' => 'KCB 456B', 'type' => 'pickup', 'capacity_kg' => 1200, 'transporter_id' => $transporter->id, 'driver_name' => 'Brian Kamau', 'driver_phone' => '0755000003']);
        DeliveryRoute::create(['name' => 'Kakamega – Nairobi', 'origin' => 'Kakamega', 'destination' => 'Nairobi', 'distance_km' => 400, 'estimated_hours' => 7]);
        DeliveryRoute::create(['name' => 'Bungoma – Nairobi', 'origin' => 'Bungoma', 'destination' => 'Nairobi', 'distance_km' => 470, 'estimated_hours' => 8.5]);

        $procurements = app(ProcurementService::class);
        $orders = app(OrderService::class);
        $finance = app(FinanceService::class);
        $logistics = app(LogisticsService::class);
        $p = fn (string $name) => Product::where('name', $name)->firstOrFail();

        // Fully processed procurements: request → confirm → receive → inspect (adds stock, creates liabilities).
        $deliveries = [
            [$farmers[0], $kakamega, ['Tomatoes' => [600, 44], 'Onions' => [500, 50], 'Kale' => [400, 8]]],
            [$farmers[2], $kakamega, ['Cabbages' => [300, 24], 'Carrots' => [250, 40], 'Spinach' => [350, 8]]],
            [$farmers[4], $kakamega, ['Bananas' => [400, 30], 'Potatoes' => [600, 34], 'Avocados' => [150, 60]]],
            [$farmers[1], $bungoma, ['Beans' => [500, 108], 'Sorghum' => [300, 50]]],
            [$farmers[5], $bungoma, ['Groundnuts' => [150, 138], 'Cowpeas' => [200, 100]]],
        ];

        foreach ($deliveries as $i => [$farmer, $centre, $lines]) {
            $proc = $procurements->save(
                ['farmer_id' => $farmer->id, 'collection_centre_id' => $centre->id, 'procurement_date' => now()->subDays(8 - $i)->toDateString()],
                collect($lines)->map(fn ($v, $name) => ['product_id' => $p($name)->id, 'quantity' => $v[0], 'unit_price' => $v[1]])->values()->all(),
            );
            $procurements->confirm($proc);
            $procurements->receive($proc->refresh(), $proc->items->mapWithKeys(fn ($item) => [$item->id => (float) $item->quantity * ($i === 2 ? 0.97 : 1)])->all());
            $procurements->inspect($proc->refresh(), $proc->items->mapWithKeys(fn ($item) => [$item->id => ['accepted' => round((float) $item->received_quantity * 0.95, 2), 'grade' => $i % 2 ? 'B' : 'A', 'comments' => 'Demo inspection']])->all());
        }

        // Pay two of the settlements.
        foreach (Procurement::where('status', 'inspected')->orderBy('id')->take(2)->get() as $proc) {
            $finance->settle($proc, (float) $proc->total_amount, 'mpesa', 'DEMO'.$proc->id, now()->subDays(2)->toDateString());
        }
        $part = Procurement::where('status', 'inspected')->orderBy('id')->skip(2)->first();
        $finance->settle($part, round((float) $part->total_amount / 2, 2), 'bank_transfer', 'BANKDEMO', now()->subDay()->toDateString());

        // Orders in different stages.
        $place = fn (Customer $customer, array $lines, int $days = 1) => $orders->place($customer, collect($lines)->map(fn ($qty, $name) => ['product_id' => $p($name)->id, 'quantity' => $qty])->values()->all(), ['delivery_address' => $customer->delivery_address, 'expected_delivery_date' => now()->addDays($days)->toDateString(), 'delivery_fee' => 1500]);

        $delivered = $place($customers[0], ['Tomatoes' => 200, 'Onions' => 150, 'Cabbages' => 100]);
        $orders->confirm($delivered);
        $orders->markReady($delivered->refresh());
        $dispatch = $logistics->createDispatch(['collection_centre_id' => $kakamega->id, 'vehicle_id' => $vehicle->id, 'destination' => 'Nairobi', 'departure_at' => now()->subHours(10), 'expected_arrival_at' => now()->subHours(2), 'transport_cost' => 18000], [$delivered->id]);
        $logistics->depart($dispatch);
        $delivery = $delivered->refresh()->deliveries()->first();
        $logistics->markInTransit($dispatch->refresh());
        $logistics->updateDelivery($delivery->refresh(), \App\Enums\DeliveryStatus::Delivered, ['recipient_name' => 'Store Manager', 'notes' => 'Received in good condition']);
        $finance->recordPayment($delivered->refresh()->invoice()->first(), (float) $delivered->total, 'bank_transfer', 'DEMOPAY1');

        $processing = $place($customers[1], ['Potatoes' => 200, 'Kale' => 100, 'Carrots' => 80]);
        $orders->confirm($processing);

        $shortfall = $place($customers[2], ['Beans' => 400, 'Maize' => 20]);
        $orders->confirm($shortfall); // beans in stock, maize is not: raises a procurement request

        $place($customers[3], ['Spinach' => 60, 'Capsicum' => 25]); // pending confirmation
        $place($customers[4], ['Rice' => 200, 'Green grams' => 100]); // pending confirmation (nothing in stock)

        // One expense.
        $finance->recordExpense(['category' => 'storage', 'description' => 'Cold room rental – Kakamega', 'amount' => 12000, 'expense_date' => now()->subDays(3)->toDateString(), 'payment_method' => 'bank_transfer']);

        Auth::logout();
    }
}
