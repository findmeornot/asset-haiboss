<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Classification;
use App\Services\InventoryNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KodeBarangTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // These tests assert absolute sequence values (INV0000001, INV0000002, ...).
        // RefreshDatabase only rolls back what THIS test commits — it does not clean up
        // data left behind by other test files using DatabaseTruncation (e.g.
        // AssetImportUpsertTest), which truncates in its own setUp, not in tearDown.
        // Reset the shared 'INV' sequence counter and any leftover INV-formatted assets
        // explicitly so these assertions are order-independent regardless of what ran before.
        DB::table('inventory_number_sequences')->where('name', 'INV')->delete();

        // Leftover assets may have FK-restricted children (status/location history, photos,
        // mutation items, etc.) from whatever DatabaseTruncation test committed them — disable
        // FK checks just for this forced cleanup so it doesn't fail on those constraints.
        Schema::disableForeignKeyConstraints();
        Asset::withTrashed()->whereRaw("inventory_number REGEXP '^INV[0-9]+$'")->forceDelete();
        Schema::enableForeignKeyConstraints();
    }

    public function test_asset_baru_mendapat_kode_barang_inv0000001()
    {
        $kode = InventoryNumberGenerator::generate();
        $this->assertEquals('INV0000001', $kode);
    }

    public function test_asset_berikutnya_mendapat_inv0000002()
    {
        InventoryNumberGenerator::generate(); // First
        $kode2 = InventoryNumberGenerator::generate(); // Second
        $this->assertEquals('INV0000002', $kode2);
    }

    public function test_classification_berbeda_tidak_memengaruhi_sequence()
    {
        // Because the generator doesn't even take classification anymore,
        // it fundamentally cannot affect the sequence.
        $kode1 = InventoryNumberGenerator::generate();
        $kode2 = InventoryNumberGenerator::generate();
        
        $this->assertNotEquals($kode1, $kode2);
        $this->assertEquals('INV0000002', $kode2);
    }

    public function test_category_berbeda_tidak_memengaruhi_sequence()
    {
        // Same as above
        $kode1 = InventoryNumberGenerator::generate();
        $kode2 = InventoryNumberGenerator::generate();
        $this->assertEquals('INV0000002', $kode2);
    }

    public function test_mengubah_classification_atau_category_tidak_mengubah_kode_barang()
    {
        // firstOrCreate: baris ini harus tetap order-independent walaupun classification
        // 'aset'/'inventaris' sudah dibuat oleh test file lain (mis. AssetImportUpsertTest)
        // yang berjalan lebih dulu dan memakai trait DatabaseTruncation (tidak rollback).
        $class1 = Classification::firstOrCreate(['slug' => 'aset'], ['name' => 'Aset']);
        $class2 = Classification::firstOrCreate(['slug' => 'inventaris'], ['name' => 'Inventaris']);
        $cat1 = Category::firstOrCreate(['code' => 'IT'], ['name' => 'IT']);
        $cat2 = Category::firstOrCreate(['code' => 'GD'], ['name' => 'Gedung']);
        
        $cat1->classifications()->syncWithoutDetaching([$class1->id]);
        $cat2->classifications()->syncWithoutDetaching([$class2->id]);

        $asset = Asset::factory()->create([
            'classification_id' => $class1->id,
            'category_id' => $cat1->id,
            'inventory_number' => 'INV0000001'
        ]);

        $this->assertEquals('INV0000001', $asset->inventory_number);

        // Update category and classification
        $asset->update([
            'classification_id' => $class2->id,
            'category_id' => $cat2->id,
        ]);

        $asset->refresh();

        // Kode Barang should remain the same
        $this->assertEquals('INV0000001', $asset->inventory_number);
    }

    public function test_soft_delete_tidak_menyebabkan_kode_barang_dapat_digunakan_ulang()
    {
        $asset = Asset::factory()->create([
            'inventory_number' => 'INV0000001'
        ]);
        
        // Ensure the sequence is updated past 001
        // Usually creating via factory won't trigger the generator explicitly,
        // so let's call the generator twice to simulate sequence state.
        InventoryNumberGenerator::generate(); // will generate INV0000002 because INV0000001 exists

        $asset->delete();
        $this->assertSoftDeleted($asset);

        // Now generate a new one
        $kodeBaru = InventoryNumberGenerator::generate();
        
        // It MUST NOT reuse INV0000001
        $this->assertEquals('INV0000003', $kodeBaru);
    }

    public function test_barcode_tetap_independen()
    {
        $asset = Asset::factory()->create([
            'inventory_number' => 'INV0000123'
        ]);

        $this->assertNotNull($asset->barcode);
        $this->assertNotEquals($asset->inventory_number, $asset->barcode);
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $asset->barcode);
    }
}
