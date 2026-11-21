<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $t) {
            $t->string('slug')->nullable()->unique()->after('name');
            $t->string('meta_title')->nullable();
            $t->string('meta_description', 320)->nullable();
            $t->text('seo_text')->nullable(); // intro copy shown on the public category page
        });

        Schema::table('products', function (Blueprint $t) {
            $t->string('slug')->nullable()->unique()->after('name');
            $t->string('meta_title')->nullable();
            $t->string('meta_description', 320)->nullable();
        });

        Schema::create('faqs', function (Blueprint $t) {
            $t->id();
            $t->string('question');
            $t->text('answer');
            $t->unsignedInteger('sort_order')->default(0)->index();
            $t->boolean('is_published')->default(true);
            $t->boolean('show_on_home')->default(false);
            $t->timestamps();
        });

        // Give every existing row a unique slug so public URLs work immediately.
        foreach (['product_categories', 'products'] as $table) {
            $used = [];

            foreach (DB::table($table)->orderBy('id')->get(['id', 'name']) as $row) {
                $base = Str::slug($row->name) ?: $table.'-'.$row->id;
                $slug = $base;
                $i = 2;

                while (isset($used[$slug])) {
                    $slug = $base.'-'.$i++;
                }

                $used[$slug] = true;
                DB::table($table)->where('id', $row->id)->update(['slug' => $slug]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');

        Schema::table('products', function (Blueprint $t) {
            $t->dropColumn(['slug', 'meta_title', 'meta_description']);
        });

        Schema::table('product_categories', function (Blueprint $t) {
            $t->dropColumn(['slug', 'meta_title', 'meta_description', 'seo_text']);
        });
    }
};
