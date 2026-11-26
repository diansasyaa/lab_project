<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestPackageSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['LAB-0004', 'Dimensional change in home laundry'],
            ['LAB-0005', 'Spirality / distortion after washing'],
            ['LAB-0001', 'Appearance after wash of garment'],
            ['LAB-0038', 'Color migration fastness'],
            ['LAB-0057', 'Attachment Strength'],
            ['LAB-0012', 'Seam Slippage'],
            ['LAB-0013', 'Seam Breakage'],
            ['LAB-0022', 'Odour Test'],
            ['LAB-0047', 'Water Absorbency (drop test)'],
            ['LAB-0045', 'Wicking Height'],
            ['LAB-0049', 'Evaporation Rate'],
        ];

        $itemIds = [];
        foreach ($items as [$code, $name]) {
            DB::table('test_items')->updateOrInsert(
                ['code' => $code],
                ['name' => $name, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()],
            );
            $itemIds[$code] = DB::table('test_items')->where('code', $code)->value('id');
        }

        $packages = [
            [
                'name' => 'Basic Garment',
                'slug' => 'basic-garment',
                'codes' => ['LAB-0004', 'LAB-0005', 'LAB-0001', 'LAB-0038', 'LAB-0057', 'LAB-0012', 'LAB-0013', 'LAB-0022'],
            ],
            [
                'name' => 'Garment Climacool / flashdry / Quick dry',
                'slug' => 'garment-climacool-flashdry-quick-dry',
                'codes' => ['LAB-0004', 'LAB-0005', 'LAB-0001', 'LAB-0038', 'LAB-0057', 'LAB-0012', 'LAB-0013', 'LAB-0022', 'LAB-0047', 'LAB-0045', 'LAB-0049'],
            ],
        ];

        foreach ($packages as $packageData) {
            $codes = $packageData['codes'];
            unset($packageData['codes']);
            DB::table('test_packages')->updateOrInsert(
                ['slug' => $packageData['slug']],
                [...$packageData, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()],
            );
            $packageId = DB::table('test_packages')->where('slug', $packageData['slug'])->value('id');

            DB::table('package_test_item')->where('test_package_id', $packageId)->delete();
            foreach ($codes as $index => $code) {
                DB::table('package_test_item')->insert([
                    'test_package_id' => $packageId,
                    'test_item_id' => $itemIds[$code],
                    'sort_order' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
