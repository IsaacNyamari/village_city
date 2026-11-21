<?php

namespace Database\Seeders;

use App\Models\County;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

/** Counties, commodity categories and the starting commodity catalogue (safe to run in production). */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $counties = [
            ['Kakamega', '037', 'Western'], ['Vihiga', '038', 'Western'], ['Bungoma', '039', 'Western'], ['Trans Nzoia', '026', 'Rift Valley'],
            ['Busia', '040', 'Western'], ['Kisumu', '042', 'Nyanza'], ['Kisii', '045', 'Nyanza'], ['Nyamira', '046', 'Nyanza'],
            ['Siaya', '041', 'Nyanza'], ['Homa Bay', '043', 'Nyanza'], ['Migori', '044', 'Nyanza'],
        ];

        foreach ($counties as [$name, $code, $region]) {
            County::firstOrCreate(['name' => $name], ['code' => $code, 'region' => $region, 'is_sourcing' => true, 'status' => 'active']);
        }

        $categories = [
            'Village City Fresh' => 'Fresh fruits and vegetables',
            'Village City Cereals and Dry Foods' => 'Cereals, legumes and other dry foods',
            'Village City Institutional Supply' => 'Bulk produce for institutional buyers',
        ];

        foreach ($categories as $name => $description) {
            ProductCategory::firstOrCreate(['name' => $name], ['description' => $description]);
        }

        // name => [category, unit, buying, selling, min stock, shelf life days (null = non-perishable)]
        $products = [
            'Tomatoes' => ['Village City Fresh', 'kg', 45, 70, 200, 7], 'Onions' => ['Village City Fresh', 'kg', 50, 75, 200, 30],
            'Cabbages' => ['Village City Fresh', 'piece', 25, 45, 150, 14], 'Kale' => ['Village City Fresh', 'bunch', 8, 15, 300, 3],
            'Spinach' => ['Village City Fresh', 'bunch', 8, 15, 300, 3], 'Carrots' => ['Village City Fresh', 'kg', 40, 65, 150, 14],
            'Capsicum' => ['Village City Fresh', 'kg', 70, 110, 80, 7], 'Bananas' => ['Village City Fresh', 'kg', 30, 50, 200, 6],
            'Avocados' => ['Village City Fresh', 'kg', 60, 100, 100, 7], 'Potatoes' => ['Village City Fresh', 'kg', 35, 55, 300, 30],
            'Maize' => ['Village City Cereals and Dry Foods', 'bag_90kg', 3200, 3900, 50, null], 'Beans' => ['Village City Cereals and Dry Foods', 'kg', 110, 150, 300, null],
            'Rice' => ['Village City Cereals and Dry Foods', 'kg', 120, 160, 300, null], 'Green grams' => ['Village City Cereals and Dry Foods', 'kg', 120, 160, 200, null],
            'Sorghum' => ['Village City Cereals and Dry Foods', 'kg', 50, 75, 200, null], 'Millet' => ['Village City Cereals and Dry Foods', 'kg', 60, 90, 200, null],
            'Groundnuts' => ['Village City Cereals and Dry Foods', 'kg', 140, 190, 100, null], 'Wheat' => ['Village City Cereals and Dry Foods', 'kg', 55, 80, 200, null],
            'Cowpeas' => ['Village City Cereals and Dry Foods', 'kg', 100, 140, 150, null], 'Lentils' => ['Village City Cereals and Dry Foods', 'kg', 130, 175, 100, null],
        ];

        foreach ($products as $name => [$category, $unit, $buy, $sell, $min, $shelf]) {
            Product::firstOrCreate(['name' => $name], [
                'product_category_id' => ProductCategory::where('name', $category)->value('id'),
                'unit' => $unit, 'buying_price' => $buy, 'selling_price' => $sell, 'min_stock_level' => $min,
                'is_perishable' => $shelf !== null, 'shelf_life_days' => $shelf,
                'storage_requirements' => $shelf !== null ? 'Cool, ventilated storage' : 'Dry, pest-free storage',
            ]);
        }
    }
}
