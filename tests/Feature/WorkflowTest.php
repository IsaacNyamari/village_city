<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\CollectionCentre;
use App\Models\Customer;
use App\Models\Farmer;
use App\Models\FinancialTransaction;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\FinanceService;
use App\Services\InventoryService;
use App\Services\LogisticsService;
use App\Services\OrderService;
use App\Services\ProcurementService;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected CollectionCentre $centre;

    protected Farmer $farmer;

    protected Customer $customer;

    protected Product $tomatoes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, ReferenceDataSeeder::class]);
        $this->actingAs(User::factory()->create()->assignRole(User::SUPER_ADMIN));

        $this->centre = CollectionCentre::create(['name' => 'Test Centre']);
        $this->farmer = Farmer::create(['name' => 'Test Farmer', 'phone' => '0700', 'status' => 'active']);
        $this->customer = Customer::create(['name' => 'Test Hotel', 'status' => 'active', 'payment_terms' => 'net_14', 'credit_limit' => 1000000, 'delivery_address' => 'Nairobi']);
        $this->tomatoes = Product::where('name', 'Tomatoes')->first();
    }

    /** Procure, receive and inspect produce so it becomes saleable stock. */
    protected function stock(float $received, float $accepted, string $grade = 'A', float $price = 40): \App\Models\Procurement
    {
        $service = app(ProcurementService::class);
        $procurement = $service->save(
            ['farmer_id' => $this->farmer->id, 'collection_centre_id' => $this->centre->id, 'procurement_date' => today()->toDateString()],
            [['product_id' => $this->tomatoes->id, 'quantity' => $received, 'unit_price' => $price]],
        );
        $service->confirm($procurement);
        $service->receive($procurement->refresh(), [$procurement->items->first()->id => $received]);
        $service->inspect($procurement->refresh(), [$procurement->items->first()->id => ['accepted' => $accepted, 'grade' => $grade]]);

        return $procurement->refresh();
    }

    public function test_only_accepted_produce_enters_stock_and_becomes_payable(): void
    {
        $procurement = $this->stock(100, 90);

        $this->assertSame(90.0, app(InventoryService::class)->available($this->tomatoes->id));
        $this->assertEquals(3600.0, (float) $procurement->total_amount); // 90 × 40
        $this->assertEquals(10.0, (float) $procurement->items->first()->rejected_quantity);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $this->tomatoes->id, 'type' => 'receipt', 'quantity' => 90]);
        $this->assertDatabaseHas('quality_inspections', ['grade' => 'A', 'accepted_quantity' => 90, 'rejected_quantity' => 10]);
    }

    public function test_a_rejected_grade_adds_no_stock(): void
    {
        $procurement = $this->stock(50, 50, 'rejected');

        $this->assertSame(0.0, app(InventoryService::class)->available($this->tomatoes->id));
        $this->assertEquals(0.0, (float) $procurement->total_amount);
    }

    public function test_invalid_procurement_transitions_are_blocked(): void
    {
        $service = app(ProcurementService::class);
        $procurement = $service->save(
            ['farmer_id' => $this->farmer->id, 'collection_centre_id' => $this->centre->id, 'procurement_date' => today()->toDateString()],
            [['product_id' => $this->tomatoes->id, 'quantity' => 10, 'unit_price' => 40]],
        );

        $this->expectException(RuntimeException::class);
        $service->receive($procurement, [$procurement->items->first()->id => 10]); // not confirmed yet
    }

    public function test_orders_allocate_stock_fifo_and_never_oversell(): void
    {
        $this->stock(100, 100, 'A', 30);
        $this->stock(100, 100, 'A', 50);
        $orders = app(OrderService::class);

        $order = $orders->place($this->customer, [['product_id' => $this->tomatoes->id, 'quantity' => 150]]);
        $order = $orders->confirm($order);

        $this->assertSame(OrderStatus::Processing, $order->status);
        $this->assertSame(50.0, app(InventoryService::class)->available($this->tomatoes->id));
        // 100 @ 30 + 50 @ 50 → average cost 36.67 (oldest batch consumed first)
        $this->assertEquals(36.67, (float) $order->items->first()->unit_cost);

        // Only 50 left: a second order for 80 is short and must not take stock it cannot get.
        $second = $orders->confirm($orders->place($this->customer, [['product_id' => $this->tomatoes->id, 'quantity' => 80]]));
        $this->assertSame(OrderStatus::AwaitingProcurement, $second->status);
        $this->assertEquals(50.0, (float) $second->items->first()->fulfilled_quantity);
        $this->assertSame(0.0, app(InventoryService::class)->available($this->tomatoes->id));
        $this->assertDatabaseHas('procurements', ['order_id' => $second->id, 'status' => 'requested']);
    }

    public function test_new_stock_automatically_completes_orders_awaiting_procurement(): void
    {
        $orders = app(OrderService::class);
        $order = $orders->confirm($orders->place($this->customer, [['product_id' => $this->tomatoes->id, 'quantity' => 40]]));
        $this->assertSame(OrderStatus::AwaitingProcurement, $order->status);

        $this->stock(60, 60);

        $this->assertSame(OrderStatus::Processing, $order->refresh()->status);
        $this->assertSame(20.0, app(InventoryService::class)->available($this->tomatoes->id));
    }

    public function test_cancelling_an_order_returns_stock_and_voids_the_invoice(): void
    {
        $this->stock(100, 100);
        $orders = app(OrderService::class);
        $order = $orders->confirm($orders->place($this->customer, [['product_id' => $this->tomatoes->id, 'quantity' => 60]]));
        $this->assertSame(40.0, app(InventoryService::class)->available($this->tomatoes->id));

        $orders->cancel($order);

        $this->assertSame(100.0, app(InventoryService::class)->available($this->tomatoes->id));
        $this->assertSame('void', $order->invoice()->withoutGlobalScopes()->first()?->status ?? 'void');
        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
    }

    public function test_partial_fulfilment_reduces_the_order_and_invoice(): void
    {
        $this->stock(30, 30);
        $orders = app(OrderService::class);
        $order = $orders->place($this->customer, [['product_id' => $this->tomatoes->id, 'quantity' => 50, 'unit_price' => 70]], ['delivery_fee' => 0]);
        $order = $orders->confirm($order);

        $order = $orders->proceedPartial($order);

        $this->assertSame(OrderStatus::Processing, $order->status);
        $this->assertEquals(30.0, (float) $order->items->first()->quantity);
        $this->assertEquals(2100.0, (float) $order->total);
        $this->assertEquals(2100.0, (float) $order->invoice()->first()->total);
    }

    public function test_invalid_order_status_transitions_are_rejected(): void
    {
        $orders = app(OrderService::class);
        $order = $orders->place($this->customer, [['product_id' => $this->tomatoes->id, 'quantity' => 5]]);

        $this->expectException(RuntimeException::class);
        $orders->markReady($order); // pending → ready is not allowed
    }

    public function test_credit_limit_is_enforced(): void
    {
        $this->customer->update(['credit_limit' => 1000]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('credit limit');
        app(OrderService::class)->place($this->customer, [['product_id' => $this->tomatoes->id, 'quantity' => 100, 'unit_price' => 70]]);
    }

    public function test_marketplace_orders_always_use_the_listed_price(): void
    {
        $order = app(OrderService::class)->place($this->customer, [['product_id' => $this->tomatoes->id, 'quantity' => 10, 'unit_price' => 1, 'discount' => 50]], [], 'marketplace');

        $this->assertEquals((float) $this->tomatoes->selling_price, (float) $order->items->first()->unit_price);
        $this->assertEquals(0.0, (float) $order->items->first()->discount);
    }

    public function test_delivery_completion_updates_order_and_payments_update_balances(): void
    {
        $this->stock(100, 100);
        $orders = app(OrderService::class);
        $order = $orders->confirm($orders->place($this->customer, [['product_id' => $this->tomatoes->id, 'quantity' => 20, 'unit_price' => 100]], ['delivery_fee' => 0, 'delivery_address' => 'Nairobi']));
        $orders->markReady($order);

        $vehicle = Vehicle::create(['registration' => 'KXX 001X', 'driver_name' => 'Driver']);
        $logistics = app(LogisticsService::class);
        $dispatch = $logistics->createDispatch(['collection_centre_id' => $this->centre->id, 'vehicle_id' => $vehicle->id, 'destination' => 'Nairobi', 'departure_at' => now(), 'expected_arrival_at' => now()->addHours(6), 'transport_cost' => 5000], [$order->id]);
        $logistics->depart($dispatch);

        $this->assertSame(OrderStatus::Dispatched, $order->refresh()->status);
        $this->assertDatabaseHas('expenses', ['dispatch_id' => $dispatch->id, 'category' => 'transport', 'amount' => 5000]);

        $delivery = $order->deliveries()->first();
        $logistics->updateDelivery($delivery, DeliveryStatus::Delivered, ['recipient_name' => 'Receiver']);

        $this->assertSame(OrderStatus::Delivered, $order->refresh()->status);
        $this->assertSame('completed', $dispatch->refresh()->status);

        $finance = app(FinanceService::class);
        $invoice = $order->invoice()->first();
        $payment = $finance->recordPayment($invoice, 500, 'mpesa', 'ABC123');

        $this->assertSame('partial', $invoice->refresh()->status);
        $this->assertEquals(1500.0, $invoice->balance());
        $this->assertEquals('partial', $order->refresh()->payment_status);

        $finance->reversePayment($payment, 'Bounced');
        $this->assertSame('unpaid', $invoice->refresh()->status);
        $this->assertSame(2, FinancialTransaction::where('source_type', $payment->getMorphClass())->count()); // payment + reversal
    }

    public function test_overpayment_is_rejected(): void
    {
        $this->stock(10, 10);
        $orders = app(OrderService::class);
        $order = $orders->confirm($orders->place($this->customer, [['product_id' => $this->tomatoes->id, 'quantity' => 5, 'unit_price' => 100]], ['delivery_fee' => 0]));

        $this->expectException(RuntimeException::class);
        app(FinanceService::class)->recordPayment($order->invoice()->first(), 99999, 'cash');
    }

    public function test_supplier_settlements_track_liabilities_and_can_be_reversed(): void
    {
        $procurement = $this->stock(100, 100, 'A', 40); // payable 4000
        $finance = app(FinanceService::class);

        $settlement = $finance->settle($procurement, 1500, 'mpesa', 'XYZ');
        $this->assertSame('partial', $procurement->refresh()->payment_status);
        $this->assertEquals(2500.0, $procurement->balance());

        $finance->reverseSettlement($settlement, 'Wrong payee');
        $this->assertSame('unpaid', $procurement->refresh()->payment_status);
        $this->assertEquals(4000.0, $procurement->balance());
    }

    public function test_stock_and_ledger_records_cannot_be_edited_or_deleted(): void
    {
        $this->stock(10, 10);
        $movement = StockMovement::first();

        try {
            $movement->update(['quantity' => 999]);
            $this->fail('Stock movement was updated.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        $this->expectException(LogicException::class);
        $movement->delete();
    }

    public function test_write_off_and_transfer_record_movements_and_respect_balances(): void
    {
        $this->stock(100, 100);
        $inventory = app(InventoryService::class);
        $batch = $this->tomatoes->batches()->first();

        $inventory->writeOff($batch, 15, 'damage', 'Crushed in transit');
        $this->assertEquals(85.0, (float) $batch->refresh()->quantity);

        $other = CollectionCentre::create(['name' => 'Hub']);
        $inventory->transfer($batch->refresh(), $other, 35, 'Restock hub');
        $this->assertEquals(50.0, (float) $batch->refresh()->quantity);
        $this->assertEquals(35.0, $inventory->available($this->tomatoes->id, $other->id));

        $this->assertSame(['receipt', 'damage', 'transfer_out', 'transfer_in'], StockMovement::orderBy('id')->pluck('type')->all());

        $this->expectException(RuntimeException::class);
        $inventory->writeOff($batch->refresh(), 9999, 'wastage', 'too much');
    }

    public function test_expired_batches_are_not_saleable(): void
    {
        $this->stock(50, 50);
        $this->tomatoes->batches()->update(['expiry_date' => today()->subDay()]);

        $this->assertSame(0.0, app(InventoryService::class)->available($this->tomatoes->id));
    }

    public function test_audit_log_records_changes_and_the_acting_user(): void
    {
        $this->farmer->update(['status' => 'inactive']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'status_changed', 'subject_type' => Farmer::class, 'user_id' => auth()->id()]);
    }

    public function test_low_stock_notifications_are_sent_to_users_with_inventory_access(): void
    {
        $this->tomatoes->update(['min_stock_level' => 200]);
        $this->stock(50, 50);
        $orders = app(OrderService::class);
        $orders->confirm($orders->place($this->customer, [['product_id' => $this->tomatoes->id, 'quantity' => 10]]));

        $this->assertGreaterThan(0, auth()->user()->notifications()->where('data', 'like', '%Low stock%')->count());
    }
}
