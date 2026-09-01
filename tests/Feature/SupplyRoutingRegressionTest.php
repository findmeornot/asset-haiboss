<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Classification;
use App\Models\InventoryBalance;
use App\Models\InventoryBalanceUnit;
use App\Models\PurchaseItem;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;
use Illuminate\Support\Str;

class SupplyRoutingRegressionTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();
        // Setup Classifications globally for tests
        Classification::create(['name' => 'ASET', 'slug' => 'aset']);
        Classification::create(['name' => 'INVENTARIS', 'slug' => 'inventaris']);
        Classification::create(['name' => 'PERSEDIAAN BARANG', 'slug' => 'persediaan-barang']);
    }

    private function simulateCreationFlow(string $classificationSlug, Category $category, int $qty, string $itemName = 'Test Item')
    {
        $classification = Classification::where('slug', $classificationSlug)->first();

        $service = new \App\Services\AssetImportService();
        $rows = [
            [
                'Kategori Akuntansi' => $classification->name,
                'Kategori' => $category->name,
                'Nama Barang' => $itemName,
                'Jumlah' => $qty,
                'Harga Perolehan' => '1500000', // String formatted
                'Tahun Perolehan' => date('Y'),
                'Kepemilikan' => 'yayasan',
            ]
        ];
        
        $service->import($rows);
    }

    public function test_case_1_asset_with_asset_category()
    {
        $category = Category::create(['name' => 'Cat 1', 'type' => 'asset', 'code' => 'C1']);
        $this->simulateCreationFlow('aset', $category, 1, 'Asset 1');

        $this->assertEquals(1, Asset::where('name', 'Asset 1')->count());
        $this->assertEquals(0, InventoryBalance::where('name', 'Asset 1')->count());
    }

    public function test_case_2_asset_with_supply_category_negative_test()
    {
        $category = Category::create(['name' => 'Cat 2', 'type' => 'supply', 'code' => 'C2']);
        $this->simulateCreationFlow('aset', $category, 1, 'Asset 2');

        // MUST route to Asset Path because Classification = Aset
        $this->assertEquals(1, Asset::where('name', 'Asset 2')->count());
        $this->assertEquals(0, InventoryBalance::where('name', 'Asset 2')->count());
    }

    public function test_case_3_inventaris_with_asset_category()
    {
        $category = Category::create(['name' => 'Cat 3', 'type' => 'asset', 'code' => 'C3']);
        $this->simulateCreationFlow('inventaris', $category, 1, 'Inv 3');

        $this->assertEquals(1, Asset::where('name', 'Inv 3')->count());
        $this->assertEquals(0, InventoryBalance::where('name', 'Inv 3')->count());
    }

    public function test_case_4_inventaris_with_supply_category_negative_test()
    {
        $category = Category::create(['name' => 'Cat 4', 'type' => 'supply', 'code' => 'C4']);
        $this->simulateCreationFlow('inventaris', $category, 1, 'Inv 4');

        // MUST route to Asset Path because Classification = Inventaris
        $this->assertEquals(1, Asset::where('name', 'Inv 4')->count());
        $this->assertEquals(0, InventoryBalance::where('name', 'Inv 4')->count());
    }

    public function test_case_5_supply_with_asset_category()
    {
        $category = Category::create(['name' => 'Cat 5', 'type' => 'asset', 'code' => 'C5']);
        $this->simulateCreationFlow('persediaan-barang', $category, 1, 'Sup 5');

        // MUST route to Supply Path because Classification = persediaan-barang
        $this->assertEquals(0, Asset::where('name', 'Sup 5')->count());
        $this->assertEquals(1, InventoryBalance::where('name', 'Sup 5')->count());
    }

    public function test_case_6_supply_with_supply_category()
    {
        $category = Category::create(['name' => 'Cat 6', 'type' => 'supply', 'code' => 'C6']);
        $this->simulateCreationFlow('persediaan-barang', $category, 1, 'Sup 6');

        $this->assertEquals(0, Asset::where('name', 'Sup 6')->count());
        $this->assertEquals(1, InventoryBalance::where('name', 'Sup 6')->count());
    }
}
