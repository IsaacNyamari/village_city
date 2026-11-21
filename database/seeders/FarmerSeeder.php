<?php

namespace Database\Seeders;

use App\Models\County;
use App\Models\Farmer;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Seeds 96 sample farmers across the western Kenya sourcing counties.
 *
 * Safe to re-run: farmers are matched on phone number, so nothing is duplicated.
 * Run on its own with:  php artisan db:seed --class=FarmerSeeder
 * (it is not part of DatabaseSeeder, so a production deploy never creates sample data by accident).
 */
class FarmerSeeder extends Seeder
{
    private const COUNT = 96;

    /** county => [sub-county => [wards/villages]] */
    private const PLACES = [
        'Kakamega' => ['Lurambi' => ['Butsotso', 'Shirere', 'Mahiakalo'], 'Malava' => ['Chemuche', 'Butali', 'Shinoyi'], 'Mumias East' => ['Lusheya', 'Shirugu']],
        'Vihiga' => ['Sabatia' => ['Lyaduywa', 'Busali', 'Chavakali'], 'Hamisi' => ['Shiru', 'Jepkoyai', 'Muhudu']],
        'Bungoma' => ['Webuye East' => ['Maraka', 'Mihuu', 'Sitikho'], 'Kimilili' => ['Kibingei', 'Maeni'], 'Tongaren' => ['Naitiri', 'Milima']],
        'Trans Nzoia' => ['Kiminini' => ['Hospital', 'Sirende'], 'Cherangany' => ['Sinyerere', 'Makutano'], 'Endebess' => ['Matumbei', 'Chepchoina']],
        'Busia' => ['Teso North' => ['Malaba', 'Ang\'urai'], 'Bunyala' => ['Budalang\'i', 'Mayenje'], 'Matayos' => ['Burumba', 'Bukhayo']],
        'Kisumu' => ['Nyando' => ['Ahero', 'Awasi'], 'Muhoroni' => ['Chemelil', 'Masogo'], 'Seme' => ['Kombewa', 'Kisumu North']],
        'Kisii' => ['Bobasi' => ['Masige', 'Nyacheki'], 'Nyaribari Chache' => ['Keumbu', 'Kiogoro'], 'Bomachoge' => ['Ibeno', 'Bomorenda']],
        'Nyamira' => ['Borabu' => ['Nyansiongo', 'Kiabonyoru'], 'Manga' => ['Magombo', 'Kemera']],
        'Siaya' => ['Gem' => ['Yala', 'Wagai'], 'Ugunja' => ['Sigomere', 'Umala'], 'Bondo' => ['Usigu', 'Yimbo']],
        'Homa Bay' => ['Rangwe' => ['Gem Wiyaga', 'Kochia'], 'Ndhiwa' => ['Kanyadoto', 'Kwabwai']],
        'Migori' => ['Rongo' => ['Kamagambo', 'Kitere'], 'Uriri' => ['Muhuru', 'Kanyamkago'], 'Awendo' => ['Kotieno', 'Sare']],
    ];

    private const FIRST = [
        'Grace', 'John', 'Mary', 'Peter', 'Jane', 'James', 'Esther', 'Samuel', 'Faith', 'David', 'Lucy', 'Joseph', 'Agnes', 'Daniel', 'Rose',
        'Patrick', 'Beatrice', 'Collins', 'Mercy', 'Kevin', 'Naomi', 'Brian', 'Ruth', 'Dennis', 'Judith', 'Erick', 'Alice', 'Vincent', 'Hellen',
        'Moses', 'Doreen', 'Isaac', 'Caroline', 'Felix', 'Sharon', 'Geoffrey', 'Pauline', 'Ochieng', 'Atieno', 'Wafula', 'Nekesa', 'Barasa',
        'Wanjala', 'Auma', 'Omondi', 'Akinyi', 'Mutua', 'Nyambura', 'Kiprop', 'Chebet',
    ];

    private const LAST = [
        'Wanjala', 'Barasa', 'Wekesa', 'Simiyu', 'Mukhwana', 'Khaemba', 'Nafula', 'Namukhula', 'Ochieng', 'Otieno', 'Odhiambo', 'Owino',
        'Onyango', 'Achieng', 'Adhiambo', 'Atieno', 'Nyamweya', 'Ombati', 'Nyakundi', 'Mokaya', 'Momanyi', 'Ondieki', 'Mogaka', 'Kerubo',
        'Kemunto', 'Mwangi', 'Kamau', 'Njeri', 'Wambui', 'Kiptoo', 'Cheruiyot', 'Lagat', 'Musyoka', 'Mulama', 'Juma', 'Were', 'Okoth',
        'Mudavadi', 'Amukowa', 'Ambani', 'Shikuku', 'Lumumba', 'Opiyo', 'Odongo', 'Anyango', 'Obuya', 'Oduor', 'Ouma', 'Sang', 'Kiplagat',
    ];

    private const PRODUCT_SETS = [
        ['Tomatoes', 'Onions', 'Kale'], ['Cabbages', 'Carrots', 'Spinach'], ['Bananas', 'Potatoes', 'Avocados'],
        ['Beans', 'Sorghum', 'Millet'], ['Maize', 'Beans', 'Groundnuts'], ['Capsicum', 'Tomatoes', 'Onions'],
        ['Kale', 'Spinach', 'Cabbages'], ['Rice', 'Green grams', 'Cowpeas'], ['Potatoes', 'Carrots', 'Cabbages'],
        ['Maize', 'Wheat', 'Lentils'], ['Avocados', 'Bananas'], ['Sorghum', 'Cowpeas', 'Groundnuts'],
    ];

    public function run(): void
    {
        if (County::count() === 0) {
            $this->call(ReferenceDataSeeder::class);
        }

        $counties = County::whereIn('name', array_keys(self::PLACES))->pluck('id', 'name');
        $products = Product::pluck('id', 'name');
        $names = array_keys(self::PLACES);
        $created = 0;

        // Fixed seed so every run (and every environment) produces the same people.
        mt_srand(20261121);

        for ($i = 1; $i <= self::COUNT; $i++) {
            $countyName = $names[($i - 1) % count($names)];
            $subCounty = array_rand(self::PLACES[$countyName]);
            $wards = self::PLACES[$countyName][$subCounty];
            $ward = $wards[mt_rand(0, count($wards) - 1)];
            $first = self::FIRST[mt_rand(0, count(self::FIRST) - 1)];
            $last = self::LAST[mt_rand(0, count(self::LAST) - 1)];
            $phone = '0734'.str_pad((string) $i, 6, '0', STR_PAD_LEFT);
            $farmSize = round(mt_rand(5, 120) / 10, 1);          // 0.5 - 12.0 acres
            $registered = now()->subDays(mt_rand(10, 700))->toDateString();
            $status = mt_rand(1, 100) <= 92 ? 'active' : 'inactive';

            $farmer = Farmer::firstOrCreate(['phone' => $phone], [
                'name' => "$first $last",
                'national_id' => (string) mt_rand(20000000, 39999999),
                'county_id' => $counties[$countyName] ?? null,
                'sub_county' => $subCounty,
                'ward' => $ward,
                'village' => $ward.' Village',
                'location' => "$ward, $subCounty",
                'farm_size_acres' => $farmSize,
                'registered_at' => $registered,
                'status' => $status,
                'notes' => 'Sample farmer (seeded).',
            ]);

            if ($farmer->wasRecentlyCreated) {
                $created++;
                $ids = collect(self::PRODUCT_SETS[($i - 1) % count(self::PRODUCT_SETS)])
                    ->map(fn ($name) => $products[$name] ?? null)->filter()->values();
                $farmer->products()->syncWithoutDetaching($ids);
            }
        }

        $this->command?->info("Farmers seeded: $created new, ".(self::COUNT - $created).' already existed.');
    }
}
