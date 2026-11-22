<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('collection_centres', 'market_days')) Schema::table('collection_centres', function (Blueprint $t) {
            $t->string('market_days')->nullable();             // e.g. "Mon, Thu"
            $t->time('listing_opens_at')->default('00:30:00'); // when daily availability goes live
            $t->time('order_cutoff_time')->default('18:00:00');
            $t->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
        });

        if (! Schema::hasColumn('products', 'packaging_type')) Schema::table('products', function (Blueprint $t) {
            $t->string('packaging_type')->nullable();
            $t->decimal('packaging_weight_kg', 10, 2)->nullable();
            $t->decimal('packaging_cost', 12, 2)->default(0);
            $t->text('packaging_spec')->nullable();
            $t->text('label_requirements')->nullable();
        });

        if (! Schema::hasColumn('vehicles', 'certification_status')) Schema::table('vehicles', function (Blueprint $t) {
            $t->string('certification_status')->default('pending')->index(); // pending|certified|rejected|suspended
            $t->date('certified_at')->nullable();
            $t->date('certification_expiry')->nullable();
            $t->json('certification_checklist')->nullable();
            $t->foreignId('certified_by')->nullable()->constrained('users')->nullOnDelete();
        });

        if (! Schema::hasColumn('order_items', 'collection_centre_id')) Schema::table('order_items', function (Blueprint $t) {
            $t->foreignId('collection_centre_id')->nullable()->constrained()->nullOnDelete();
        });

        if (! Schema::hasColumn('deliveries', 'parcel_code')) Schema::table('deliveries', function (Blueprint $t) {
            $t->string('last_mile_provider')->nullable(); // own|uber|bolt|boda
            $t->string('parcel_code')->nullable()->index();
            $t->string('rider_name')->nullable();
            $t->string('rider_phone')->nullable();
        });

        if (! Schema::hasTable('inspector_profiles')) Schema::create('inspector_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('collection_centre_id')->nullable()->unique()->constrained()->nullOnDelete(); // one per centre
            $t->string('national_id')->nullable();
            $t->string('phone')->nullable();
            $t->string('status')->default('applied')->index(); // applied|vetted|trained|active|suspended
            $t->date('vetted_at')->nullable();
            $t->date('trained_at')->nullable();
            $t->date('sop_signed_at')->nullable();
            $t->decimal('daily_fee', 12, 2)->default(0);
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        if (! Schema::hasTable('daily_listings')) Schema::create('daily_listings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('collection_centre_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->date('listing_date')->index();
            $t->decimal('quantity_available', 12, 2)->default(0);
            $t->decimal('unit_price', 14, 2)->default(0);
            $t->string('status')->default('open'); // open|closed
            $t->timestamps();
            $t->unique(['collection_centre_id', 'product_id', 'listing_date']);
        });

        if (! Schema::hasTable('dispatch_checks')) Schema::create('dispatch_checks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('dispatch_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $t->boolean('orders_match')->default(false);
            $t->boolean('labelling_ok')->default(false);
            $t->boolean('packaging_ok')->default(false);
            $t->boolean('all_cargo_loaded')->default(false);
            $t->boolean('sop_followed')->default(false);
            $t->text('notes')->nullable();
            $t->timestamp('signed_off_at')->nullable();
            $t->timestamps();
        });

        if (! Schema::hasTable('fee_schedules')) Schema::create('fee_schedules', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->string('payee');                  // inspector|transporter|farmer|last_mile|platform
            $t->string('basis')->default('flat'); // flat|per_km|per_kg|percent
            $t->decimal('amount', 14, 2)->default(0);
            $t->string('pay_timing')->nullable();
            $t->string('status')->default('active');
            $t->timestamps();
        });

        $now = now();
        DB::table('fee_schedules')->insert(array_map(fn ($r) => $r + ['status' => 'active', 'created_at' => $now, 'updated_at' => $now], [
            ['code' => 'inspector_daily', 'name' => 'Quality inspector daily fee', 'payee' => 'inspector', 'basis' => 'flat', 'amount' => 1000, 'pay_timing' => 'Same day, after dispatch sign-off'],
            ['code' => 'transport_per_km', 'name' => 'Transport per km', 'payee' => 'transporter', 'basis' => 'per_km', 'amount' => 60, 'pay_timing' => 'On delivery completion'],
            ['code' => 'farmer_payout', 'name' => 'Farmer / seller payout', 'payee' => 'farmer', 'basis' => 'percent', 'amount' => 100, 'pay_timing' => 'Within 24h of inspection'],
            ['code' => 'last_mile_flat', 'name' => 'Last-mile (Uber/Bolt/boda) per parcel', 'payee' => 'last_mile', 'basis' => 'flat', 'amount' => 250, 'pay_timing' => 'On proof of delivery'],
            ['code' => 'platform_margin', 'name' => 'Platform margin', 'payee' => 'platform', 'basis' => 'percent', 'amount' => 8, 'pay_timing' => 'Deducted at order payment'],
        ]));
    }

    public function down(): void
    {
        foreach (['fee_schedules', 'dispatch_checks', 'daily_listings', 'inspector_profiles'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('deliveries', fn (Blueprint $t) => $t->dropColumn(['last_mile_provider', 'parcel_code', 'rider_name', 'rider_phone']));
        Schema::table('order_items', fn (Blueprint $t) => $t->dropConstrainedForeignId('collection_centre_id'));
        if (! Schema::hasColumn('vehicles', 'certification_status')) Schema::table('vehicles', function (Blueprint $t) {
            $t->dropConstrainedForeignId('certified_by');
            $t->dropColumn(['certification_status', 'certified_at', 'certification_expiry', 'certification_checklist']);
        });
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['packaging_type', 'packaging_weight_kg', 'packaging_cost', 'packaging_spec', 'label_requirements']));
        if (! Schema::hasColumn('collection_centres', 'market_days')) Schema::table('collection_centres', function (Blueprint $t) {
            $t->dropConstrainedForeignId('inspector_id');
            $t->dropColumn(['market_days', 'listing_opens_at', 'order_cutoff_time']);
        });
    }
};
