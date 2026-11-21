<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counties', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('code', 10)->nullable();
            $t->string('region')->nullable();
            $t->boolean('is_sourcing')->default(true);
            $t->string('status')->default('active')->index();
            $t->timestamps();
        });

        Schema::create('sub_counties', function (Blueprint $t) {
            $t->id();
            $t->foreignId('county_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('wards')->nullable();
            $t->timestamps();
            $t->unique(['county_id', 'name']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('county_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $t->string('phone')->nullable()->after('email');
            $t->boolean('is_active')->default(true)->after('phone');
            $t->timestamp('last_login_at')->nullable();
        });

        Schema::create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->string('number')->nullable()->unique();
            $t->string('business_name');
            $t->string('contact_person')->nullable();
            $t->string('phone')->nullable();
            $t->string('email')->nullable();
            $t->string('location')->nullable();
            $t->foreignId('county_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type')->default('individual')->index();
            $t->string('payment_terms')->nullable();
            $t->string('status')->default('active')->index();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('collection_centres', function (Blueprint $t) {
            $t->id();
            $t->string('code')->nullable()->unique();
            $t->string('name');
            $t->string('type')->default('collection_centre')->index();
            $t->foreignId('county_id')->nullable()->constrained()->nullOnDelete();
            $t->string('sub_county')->nullable();
            $t->string('ward')->nullable();
            $t->string('location')->nullable();
            $t->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('contact_phone')->nullable();
            $t->decimal('capacity_kg', 12, 2)->nullable();
            $t->string('status')->default('active')->index();
            $t->timestamps();
        });

        Schema::create('collection_centre_user', function (Blueprint $t) {
            $t->foreignId('collection_centre_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->primary(['collection_centre_id', 'user_id']);
        });

        Schema::create('product_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('description')->nullable();
            $t->string('status')->default('active');
            $t->timestamps();
        });

        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('code')->nullable()->unique();
            $t->string('name');
            $t->foreignId('product_category_id')->constrained()->restrictOnDelete();
            $t->text('description')->nullable();
            $t->string('unit', 20)->default('kg');
            $t->decimal('buying_price', 14, 2)->default(0);
            $t->decimal('selling_price', 14, 2)->default(0);
            $t->decimal('min_stock_level', 12, 2)->default(0);
            $t->decimal('min_order_qty', 12, 2)->default(1);
            $t->boolean('is_perishable')->default(true);
            $t->unsignedInteger('shelf_life_days')->nullable();
            $t->string('storage_requirements')->nullable();
            $t->string('image_path')->nullable();
            $t->string('status')->default('active')->index();
            $t->timestamps();
        });

        Schema::create('product_supplier', function (Blueprint $t) {
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $t->primary(['product_id', 'supplier_id']);
        });

        Schema::create('farmers', function (Blueprint $t) {
            $t->id();
            $t->string('number')->nullable()->unique();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('phone')->nullable()->index();
            $t->string('email')->nullable();
            $t->string('national_id')->nullable();
            $t->foreignId('county_id')->nullable()->constrained()->nullOnDelete();
            $t->string('sub_county')->nullable();
            $t->string('ward')->nullable();
            $t->string('village')->nullable();
            $t->string('location')->nullable();
            $t->string('gps_coordinates')->nullable();
            $t->decimal('farm_size_acres', 10, 2)->nullable();
            $t->foreignId('cooperative_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $t->foreignId('collection_centre_id')->nullable()->constrained()->nullOnDelete();
            $t->date('registered_at')->nullable();
            $t->string('status')->default('active')->index();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('farmer_product', function (Blueprint $t) {
            $t->foreignId('farmer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->primary(['farmer_id', 'product_id']);
        });

        Schema::create('harvest_schedules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('farmer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('season')->nullable();
            $t->date('expected_harvest_date');
            $t->decimal('expected_quantity', 12, 2)->default(0);
            $t->string('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('number')->nullable()->unique();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('contact_person')->nullable();
            $t->string('phone')->nullable();
            $t->string('email')->nullable();
            $t->string('type')->default('household')->index();
            $t->string('billing_address')->nullable();
            $t->string('delivery_address')->nullable();
            $t->string('county')->nullable();
            $t->string('payment_terms')->nullable();
            $t->decimal('credit_limit', 14, 2)->default(0);
            $t->string('status')->default('active')->index();
            $t->timestamps();
        });

        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->nullable()->unique();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->string('status')->default('pending_confirmation')->index();
            $t->string('payment_status')->default('unpaid')->index();
            $t->string('source')->default('internal');
            $t->decimal('subtotal', 14, 2)->default(0);
            $t->decimal('discount', 14, 2)->default(0);
            $t->decimal('delivery_fee', 14, 2)->default(0);
            $t->decimal('total', 14, 2)->default(0);
            $t->decimal('amount_paid', 14, 2)->default(0);
            $t->string('delivery_address')->nullable();
            $t->date('expected_delivery_date')->nullable();
            $t->text('instructions')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamps();
            $t->index('created_at');
        });

        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->decimal('quantity', 12, 2);
            $t->decimal('fulfilled_quantity', 12, 2)->default(0);
            $t->decimal('unit_price', 14, 2);
            $t->decimal('unit_cost', 14, 2)->default(0);
            $t->decimal('discount', 14, 2)->default(0);
            $t->decimal('total', 14, 2)->default(0);
            $t->timestamps();
        });

        Schema::create('procurements', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->nullable()->unique();
            $t->foreignId('farmer_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('collection_centre_id')->constrained()->restrictOnDelete();
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->date('procurement_date');
            $t->string('status')->default('requested')->index();
            $t->decimal('total_amount', 14, 2)->default(0);
            $t->decimal('amount_paid', 14, 2)->default(0);
            $t->string('payment_status')->default('unpaid')->index();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('received_at')->nullable();
            $t->timestamp('inspected_at')->nullable();
            $t->timestamps();
            $t->index('procurement_date');
        });

        Schema::create('procurement_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('procurement_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->decimal('quantity', 12, 2);
            $t->decimal('unit_price', 14, 2);
            $t->decimal('received_quantity', 12, 2)->default(0);
            $t->decimal('accepted_quantity', 12, 2)->default(0);
            $t->decimal('rejected_quantity', 12, 2)->default(0);
            $t->string('grade')->nullable();
            $t->decimal('total', 14, 2)->default(0);
            $t->timestamps();
        });

        Schema::create('quality_inspections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('procurement_id')->constrained()->cascadeOnDelete();
            $t->foreignId('procurement_item_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->decimal('received_quantity', 12, 2);
            $t->decimal('accepted_quantity', 12, 2);
            $t->decimal('rejected_quantity', 12, 2);
            $t->string('grade')->index();
            $t->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('inspected_at');
            $t->text('comments')->nullable();
            $t->string('image_path')->nullable();
            $t->timestamps();
        });

        Schema::create('stock_batches', function (Blueprint $t) {
            $t->id();
            $t->string('batch_number')->nullable()->unique();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->foreignId('collection_centre_id')->constrained()->restrictOnDelete();
            $t->foreignId('procurement_item_id')->nullable()->constrained()->nullOnDelete();
            $t->date('received_at');
            $t->date('expiry_date')->nullable();
            $t->decimal('initial_quantity', 12, 2);
            $t->decimal('quantity', 12, 2);
            $t->decimal('purchase_price', 14, 2)->default(0);
            $t->string('grade')->nullable();
            $t->timestamps();
            $t->index(['product_id', 'collection_centre_id', 'received_at']);
        });

        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->foreignId('collection_centre_id')->constrained()->restrictOnDelete();
            $t->foreignId('stock_batch_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type')->index();
            $t->decimal('quantity', 12, 2); // signed: + in, - out
            $t->decimal('unit_cost', 14, 2)->default(0);
            $t->nullableMorphs('reference');
            $t->string('notes')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            $t->index('created_at');
        });

        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->string('number')->nullable()->unique();
            $t->foreignId('order_id')->constrained()->restrictOnDelete();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->date('issue_date');
            $t->date('due_date')->nullable();
            $t->decimal('total', 14, 2)->default(0);
            $t->decimal('amount_paid', 14, 2)->default(0);
            $t->string('status')->default('unpaid')->index();
            $t->timestamps();
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->nullable()->unique();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('amount', 14, 2);
            $t->string('method')->index();
            $t->string('transaction_ref')->nullable();
            $t->date('paid_at');
            $t->string('status')->default('completed')->index();
            $t->string('notes')->nullable();
            $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('supplier_settlements', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->nullable()->unique();
            $t->foreignId('procurement_id')->constrained()->restrictOnDelete();
            $t->foreignId('farmer_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('amount', 14, 2);
            $t->string('method');
            $t->string('transaction_ref')->nullable();
            $t->date('paid_at');
            $t->string('status')->default('completed')->index();
            $t->string('notes')->nullable();
            $t->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('transporters', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('contact_person')->nullable();
            $t->string('phone')->nullable();
            $t->string('email')->nullable();
            $t->string('location')->nullable();
            $t->text('contract_details')->nullable();
            $t->string('status')->default('active')->index();
            $t->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $t) {
            $t->id();
            $t->string('registration')->unique();
            $t->string('type')->nullable();
            $t->decimal('capacity_kg', 12, 2)->nullable();
            $t->foreignId('transporter_id')->nullable()->constrained()->nullOnDelete();
            $t->string('driver_name')->nullable();
            $t->string('driver_phone')->nullable();
            $t->date('insurance_expiry')->nullable();
            $t->date('inspection_expiry')->nullable();
            $t->string('status')->default('available')->index();
            $t->timestamps();
        });

        Schema::create('delivery_routes', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('origin');
            $t->string('destination');
            $t->decimal('distance_km', 10, 2)->nullable();
            $t->decimal('estimated_hours', 6, 2)->nullable();
            $t->string('status')->default('active');
            $t->timestamps();
        });

        Schema::create('dispatches', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->nullable()->unique();
            $t->foreignId('collection_centre_id')->constrained()->restrictOnDelete();
            $t->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('transporter_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('delivery_route_id')->nullable()->constrained()->nullOnDelete();
            $t->string('driver_name')->nullable();
            $t->string('driver_phone')->nullable();
            $t->string('destination')->nullable();
            $t->dateTime('departure_at')->nullable();
            $t->dateTime('expected_arrival_at')->nullable();
            $t->decimal('transport_cost', 14, 2)->default(0);
            $t->string('status')->default('scheduled')->index();
            $t->string('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('deliveries', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->nullable()->unique();
            $t->foreignId('dispatch_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('order_id')->constrained()->restrictOnDelete();
            $t->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $t->string('driver_name')->nullable();
            $t->string('origin')->nullable();
            $t->string('destination')->nullable();
            $t->dateTime('dispatched_at')->nullable();
            $t->dateTime('expected_at')->nullable();
            $t->dateTime('delivered_at')->nullable();
            $t->string('status')->default('scheduled')->index();
            $t->string('recipient_name')->nullable();
            $t->string('recipient_phone')->nullable();
            $t->string('pod_path')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('delivery_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $t->string('status');
            $t->string('note')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->nullable()->unique();
            $t->string('category')->index();
            $t->string('description');
            $t->decimal('amount', 14, 2);
            $t->date('expense_date')->index();
            $t->string('payment_method')->default('cash');
            $t->foreignId('dispatch_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status')->default('recorded')->index();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('financial_transactions', function (Blueprint $t) {
            $t->id();
            $t->date('transaction_date')->index();
            $t->string('direction')->index(); // in | out
            $t->string('category')->index();
            $t->decimal('amount', 14, 2);
            $t->string('method')->nullable();
            $t->string('reference')->nullable();
            $t->nullableMorphs('source');
            $t->string('description')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action')->index();
            $t->nullableMorphs('subject');
            $t->string('description')->nullable();
            $t->json('properties')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->timestamps();
            $t->index('created_at');
        });

        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->text('value')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'settings', 'notifications', 'audit_logs', 'financial_transactions', 'expenses', 'delivery_events',
            'deliveries', 'dispatches', 'delivery_routes', 'vehicles', 'transporters', 'supplier_settlements',
            'payments', 'invoices', 'stock_movements', 'stock_batches', 'quality_inspections', 'procurement_items',
            'procurements', 'order_items', 'orders', 'customers', 'harvest_schedules', 'farmer_product', 'farmers',
            'product_supplier', 'products', 'product_categories', 'collection_centre_user', 'collection_centres', 'suppliers',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('county_id');
            $t->dropColumn(['phone', 'is_active', 'last_login_at']);
        });

        foreach (['sub_counties', 'counties'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
