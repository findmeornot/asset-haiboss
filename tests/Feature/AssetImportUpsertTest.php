<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Classification;
use App\Models\Location;
use App\Models\PurchaseItem;
use App\Models\InventoryBalance;
use App\Services\AssetImportService;
use App\Services\InventoryNumberGenerator;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssetImportUpsertTest extends TestCase
{
    use DatabaseTruncation;

    protected AssetImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AssetImportService();
        
        DB::table('inventory_number_sequences')->insert([
            'name' => 'INV'
        ]);
        
        Campus::factory()->create(['name' => 'Kampus A']);
        $cat = Category::factory()->create(['name' => 'Laptop']);
        $class = Classification::factory()->create(['name' => 'Aset', 'slug' => 'aset']);
        $cat->classifications()->attach($class->id);
    }

    private function getValidRow(array $overrides = []): array
    {
        return array_merge([
            'Kode' => '',
            'Kategori Akuntansi' => 'Aset',
            'Kategori' => 'Laptop',
            'Nama Barang' => 'MacBook Pro',
            'Merk/Tipe' => 'Apple',
            'Nomor Seri' => 'SN123',
            'Jumlah' => '1',
            'Satuan' => 'Unit',
            'Tahun Perolehan' => '2024',
            'Sumber Dana' => 'Yayasan',
            'Gedung' => 'Kampus A',
            'Ruangan' => 'R101',
            'PIC' => 'Budi',
            'Status' => 'Aktif',
            'Kondisi' => 'Baik',
            'Harga Perolehan' => '15000000',
            'Keterangan' => 'Test',
        ], $overrides);
    }

    public function test_import_existing_kode_updates_asset_correctly()
    {
        $asset = Asset::factory()->create([
            'inventory_number' => 'INV0000001',
            'name' => 'Old Name',
        ]);

        $row = $this->getValidRow(['Kode' => 'INV0000001', 'Nama Barang' => 'New Name']);
        $errors = $this->service->validateRows([$row]);
        $this->assertEmpty($errors);

        $this->service->import([$row]);

        $asset->refresh();
        $this->assertEquals('New Name', $asset->name);
    }

    public function test_import_existing_kode_preserves_inventory_number_and_barcode()
    {
        $asset = Asset::factory()->create([
            'inventory_number' => 'INV0000001',
            'barcode' => '123456'
        ]);

        $row = $this->getValidRow(['Kode' => 'INV0000001']);
        $this->service->import([$row]);

        $asset->refresh();
        $this->assertEquals('INV0000001', $asset->inventory_number);
        $this->assertEquals('123456', $asset->barcode);
    }

    public function test_import_existing_kode_does_not_modify_purchase_history()
    {
        $purchase = \App\Models\Purchase::create([
            'purchase_date' => now(),
            'ownership' => 'yayasan',
            'total_amount' => 5000
        ]);
        
        $purchaseItem = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'name' => 'Old Item',
            'quantity' => 1,
            'unit_price' => 5000,
            'total_price' => 5000,
            'is_capitalized' => false
        ]);
        
        $asset = Asset::factory()->create([
            'inventory_number' => 'INV0000001',
            'purchase_item_id' => $purchaseItem->id
        ]);

        // Trying to update with different price/year/ownership
        $row = $this->getValidRow([
            'Kode' => 'INV0000001',
            'Harga Perolehan' => '99000000',
            'Sumber Dana' => 'Hibah',
            'Tahun Perolehan' => '2020'
        ]);
        
        $this->service->import([$row]);

        $asset->refresh();
        $this->assertEquals($purchaseItem->id, $asset->purchase_item_id);
        
        $purchaseItem->refresh();
        $this->assertEquals(5000, $purchaseItem->unit_price);

        $purchase->refresh();
        // Tahun Perolehan lama (bukan null) tidak boleh tertimpa jadi 2020.
        $this->assertNotEquals('2020-01-01', $purchase->purchase_date->format('Y-m-d'));

        // Ensure no new purchase items were created
        $this->assertEquals(1, PurchaseItem::count());
    }

    public function test_import_existing_kode_fills_empty_financial_data_without_overwriting()
    {
        // PurchaseItem/Purchase dibuat dengan data finansial masih kosong (belum diketahui).
        $purchase = \App\Models\Purchase::create([
            'purchase_date' => null,
            'ownership' => 'company',
            'total_amount' => null,
        ]);

        $purchaseItem = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'name' => 'Old Item',
            'quantity' => 1,
            'unit_price' => null,
            'total_price' => null,
            'is_capitalized' => false,
        ]);

        $asset = Asset::factory()->create([
            'inventory_number' => 'INV0000001',
            'purchase_item_id' => $purchaseItem->id,
        ]);

        $row = $this->getValidRow([
            'Kode' => 'INV0000001',
            'Harga Perolehan' => '20000000',
            'Tahun Perolehan' => '2023',
        ]);

        $this->service->import([$row]);

        $purchaseItem->refresh();
        $this->assertEquals(20000000.00, (float) $purchaseItem->unit_price);
        $this->assertEquals(20000000.00, (float) $purchaseItem->total_price); // quantity = 1

        $purchase->refresh();
        $this->assertEquals('2023-01-01', $purchase->purchase_date->format('Y-m-d'));
    }

    public function test_import_existing_kode_does_not_refill_already_known_financial_data_twice()
    {
        // Regression guard: kalau sudah keisi dari import pertama, import kedua dengan
        // nilai berbeda tidak boleh menimpa lagi.
        $purchase = \App\Models\Purchase::create([
            'purchase_date' => null,
            'ownership' => 'company',
            'total_amount' => null,
        ]);

        $purchaseItem = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'name' => 'Old Item',
            'quantity' => 1,
            'unit_price' => null,
            'total_price' => null,
            'is_capitalized' => false,
        ]);

        Asset::factory()->create([
            'inventory_number' => 'INV0000001',
            'purchase_item_id' => $purchaseItem->id,
        ]);

        // Import pertama: isi data yang masih kosong.
        $this->service->import([$this->getValidRow([
            'Kode' => 'INV0000001',
            'Harga Perolehan' => '20000000',
        ])]);

        // Import kedua: coba timpa dengan angka lain — harus ditolak/diabaikan.
        $this->service->import([$this->getValidRow([
            'Kode' => 'INV0000001',
            'Harga Perolehan' => '99000000',
        ])]);

        $purchaseItem->refresh();
        $this->assertEquals(20000000.00, (float) $purchaseItem->unit_price);
    }

    public function test_import_new_kode_not_found_is_rejected()
    {
        // Kode Barang aset baru SELALU di-generate otomatis oleh sistem. Kode terisi
        // tapi tidak match asset manapun (aktif/soft-deleted) tidak boleh dipakai untuk
        // insert baru dengan nilai eksplisit itu — harus ditolak validasi.
        $row = $this->getValidRow(['Kode' => 'INV0000005']);
        $errors = $this->service->validateRows([$row]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('tidak ditemukan', $errors[0]['message']);

        $this->assertEquals(0, Asset::count());
    }

    public function test_import_blank_kode_generates_new_kode_barang()
    {
        $row = $this->getValidRow(['Kode' => '']);
        $this->service->import([$row]);

        $this->assertDatabaseHas('assets', [
            'inventory_number' => 'INV0000001'
        ]);
    }

    public function test_import_blank_kode_with_quantity_creates_multiple_assets()
    {
        $row = $this->getValidRow(['Kode' => '', 'Jumlah' => '3']);
        $this->service->import([$row]);

        $this->assertEquals(3, Asset::count());
        $this->assertDatabaseHas('assets', ['inventory_number' => 'INV0000001']);
        $this->assertDatabaseHas('assets', ['inventory_number' => 'INV0000002']);
        $this->assertDatabaseHas('assets', ['inventory_number' => 'INV0000003']);
    }

    public function test_import_duplicate_kode_in_file_is_rejected()
    {
        // Asset harus benar-benar ada supaya baris ini murni menguji deteksi duplicate-in-file,
        // bukan tercampur dengan penolakan "Kode tidak ditemukan".
        Asset::factory()->create(['inventory_number' => 'INV0000001']);

        $row1 = $this->getValidRow(['Kode' => 'INV0000001', '_row_number' => 2]);
        $row2 = $this->getValidRow(['Kode' => 'INV0000001', '_row_number' => 3]);
        
        $errors = $this->service->validateRows([$row1, $row2]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('duplikat dengan baris ke-2', $errors[0]['message']);
    }

    public function test_import_invalid_kode_format_is_rejected()
    {
        $row = $this->getValidRow(['Kode' => 'AST-123']);
        $errors = $this->service->validateRows([$row]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Format Kode Barang tidak valid', $errors[0]['message']);
    }

    public function test_import_existing_soft_deleted_kode_is_rejected()
    {
        $asset = Asset::factory()->create(['inventory_number' => 'INV0000001']);
        $asset->delete();

        $row = $this->getValidRow(['Kode' => 'INV0000001']);
        $errors = $this->service->validateRows([$row]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('telah dihapus', $errors[0]['message']);
    }

    public function test_import_update_with_quantity_greater_than_one_is_rejected()
    {
        Asset::factory()->create(['inventory_number' => 'INV0000001']);

        $row = $this->getValidRow(['Kode' => 'INV0000001', 'Jumlah' => '2']);
        $errors = $this->service->validateRows([$row]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('tidak boleh lebih dari 1', $errors[0]['message']);
    }

    public function test_supply_import_remains_unchanged()
    {
        $class = Classification::factory()->create(['name' => 'Barang Habis Pakai', 'slug' => 'barang-habis-pakai']);
        $cat = Category::factory()->create(['name' => 'Spidol']);
        $cat->classifications()->attach($class->id);

        $row = $this->getValidRow([
            'Kode' => 'INV0000001', // should be ignored
            'Kategori Akuntansi' => 'Barang Habis Pakai',
            'Kategori' => 'Spidol',
            'Jumlah' => '10'
        ]);

        $errors = $this->service->validateRows([$row]);
        $this->assertEmpty($errors);

        $this->service->import([$row]);

        $this->assertEquals(0, Asset::count());
        $this->assertEquals(1, InventoryBalance::count());
        $this->assertEquals(10, InventoryBalance::first()->quantity);
    }

    public function test_import_update_can_change_classification_without_changing_kode()
    {
        $newClass = Classification::factory()->create(['name' => 'Inventaris', 'slug' => 'inventaris']);
        $newCat = Category::factory()->create(['name' => 'Mouse']);
        $newCat->classifications()->attach($newClass->id);

        $asset = Asset::factory()->create(['inventory_number' => 'INV0000001']);
        
        $row = $this->getValidRow([
            'Kode' => 'INV0000001',
            'Kategori Akuntansi' => 'Inventaris',
            'Kategori' => 'Mouse'
        ]);

        $this->service->import([$row]);
        $asset->refresh();

        $this->assertEquals($newClass->id, $asset->classification_id);
        $this->assertEquals($newCat->id, $asset->category_id);
        $this->assertEquals('INV0000001', $asset->inventory_number);
    }

    public function test_barcode_generation_remains_independent()
    {
        $row = $this->getValidRow(['Kode' => '']);
        $this->service->import([$row]);

        $asset = Asset::first();
        $this->assertNotNull($asset->barcode);
        $this->assertNotEquals($asset->inventory_number, $asset->barcode);
    }

    // ──────────────────────────────────────────────────────────────
    // Regression coverage for the forensic audit findings
    // ──────────────────────────────────────────────────────────────

    public function test_import_new_kode_with_quantity_greater_than_one_is_rejected()
    {
        // CRITICAL finding (audit sebelumnya): Kode valid tapi belum ada di DB + Jumlah > 1
        // dulu lolos validasi lalu gagal di unique constraint saat import(). Sekarang Kode
        // tidak ditemukan langsung ditolak tanpa peduli Jumlah — dan Jumlah>1 dengan Kode
        // terisi tetap punya penolakan sendiri sebagai defense-in-depth kedua.
        $row = $this->getValidRow(['Kode' => 'INV9999999', 'Jumlah' => '3']);

        $errors = $this->service->validateRows([$row]);
        $this->assertNotEmpty($errors);

        $messages = implode(' | ', array_column($errors, 'message'));
        $this->assertStringContainsString('tidak ditemukan', $messages);
        $this->assertStringContainsString('tidak boleh lebih dari 1', $messages);

        // Pastikan tidak ada asset yang ter-create sama sekali kalau validasi dihormati.
        $this->assertEquals(0, Asset::count());
    }

    public function test_import_new_kode_not_found_is_rejected_regardless_of_quantity()
    {
        // Regression guard: Kode tidak ditemukan tetap ditolak walau Jumlah=1 (bukan cuma
        // Jumlah>1) — insert dengan Kode eksplisit sudah dimatikan total.
        $row = $this->getValidRow(['Kode' => 'INV9999999', 'Jumlah' => '1']);

        $errors = $this->service->validateRows([$row]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('tidak ditemukan', $errors[0]['message']);
    }

    public function test_supply_import_ignores_invalid_kode_format()
    {
        $class = Classification::factory()->create(['name' => 'Barang Habis Pakai', 'slug' => 'barang-habis-pakai']);
        $cat = Category::factory()->create(['name' => 'Spidol']);
        $cat->classifications()->attach($class->id);

        $row = $this->getValidRow([
            'Kode' => 'BUKAN-KODE-VALID', // format Kode Barang Asset tidak valid, tapi harus diabaikan
            'Kategori Akuntansi' => 'Barang Habis Pakai',
            'Kategori' => 'Spidol',
            'Jumlah' => '5',
        ]);

        $errors = $this->service->validateRows([$row]);
        $this->assertEmpty($errors);

        $this->service->import([$row]);

        $this->assertEquals(0, Asset::count());
        $this->assertEquals(5, InventoryBalance::first()->quantity);
    }

    public function test_supply_import_ignores_kode_collision_with_existing_asset()
    {
        // Kode yang sama persis dengan Asset aktif tidak boleh membuat baris supply ditolak,
        // karena Barang Habis Pakai tidak menggunakan Kode Barang Asset sama sekali.
        Asset::factory()->create(['inventory_number' => 'INV0000001']);

        $class = Classification::factory()->create(['name' => 'Barang Habis Pakai', 'slug' => 'barang-habis-pakai']);
        $cat = Category::factory()->create(['name' => 'Spidol']);
        $cat->classifications()->attach($class->id);

        $row = $this->getValidRow([
            'Kode' => 'INV0000001', // collide dengan Asset aktif di atas, harus tetap diabaikan
            'Kategori Akuntansi' => 'Barang Habis Pakai',
            'Kategori' => 'Spidol',
            'Jumlah' => '2',
        ]);

        $errors = $this->service->validateRows([$row]);
        $this->assertEmpty($errors);

        $this->service->import([$row]);

        // Asset lama tidak berubah/bertambah, dan balance supply tetap terbentuk normal.
        $this->assertEquals(1, Asset::count());
        $this->assertEquals(1, InventoryBalance::count());
        $this->assertEquals(2, InventoryBalance::first()->quantity);
    }

    public function test_supply_import_ignores_soft_deleted_kode_collision()
    {
        // Kode milik Asset soft-deleted juga tidak boleh membuat baris supply ditolak.
        $asset = Asset::factory()->create(['inventory_number' => 'INV0000001']);
        $asset->delete();

        $class = Classification::factory()->create(['name' => 'Barang Habis Pakai', 'slug' => 'barang-habis-pakai']);
        $cat = Category::factory()->create(['name' => 'Spidol']);
        $cat->classifications()->attach($class->id);

        $row = $this->getValidRow([
            'Kode' => 'INV0000001',
            'Kategori Akuntansi' => 'Barang Habis Pakai',
            'Kategori' => 'Spidol',
            'Jumlah' => '1',
        ]);

        $errors = $this->service->validateRows([$row]);
        $this->assertEmpty($errors);
    }

    public function test_import_kode_with_surrounding_whitespace_and_nbsp_is_sanitized()
    {
        $asset = Asset::factory()->create(['inventory_number' => 'INV0000001']);

        // NBSP (\xC2\xA0) di sekitar Kode, seperti hasil copy-paste dari Excel/Word.
        $dirtyKode = "\u{00A0} INV0000001 \u{00A0}";

        $row = $this->getValidRow(['Kode' => $dirtyKode, 'Nama Barang' => 'Updated Via Dirty Kode']);
        $errors = $this->service->validateRows([$row]);
        $this->assertEmpty($errors);

        $this->service->import([$row]);

        $asset->refresh();
        $this->assertEquals('Updated Via Dirty Kode', $asset->name);
        $this->assertEquals('INV0000001', $asset->inventory_number);
    }
}
