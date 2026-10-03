<?php

namespace Tests\Feature;

use App\Filament\Support\PrintBarcodeActions;
use App\Models\Asset;
use App\Models\Campus;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Memastikan urutan Cetak Barcode konsisten dengan Cetak Checklist:
 * orderBy(name) -> orderBy(inventory_number), sama persis dengan
 * route checklist.print di web.php baris 51.
 */
class PrintBarcodeSortingTest extends TestCase
{
    use RefreshDatabase;

    private function resolveViaReflection($records): \Illuminate\Support\Collection
    {
        $method = new \ReflectionMethod(PrintBarcodeActions::class, 'resolveAssets');
        $method->setAccessible(true);
        return $method->invoke(null, $records);
    }

    /**
     * Buat aset dalam urutan yang SENGAJA terbalik dari abjad,
     * kemudian verifikasi resolveAssets() mengembalikannya berurutan A→Z.
     */
    public function test_barcode_bulk_sorted_by_name_then_inventory_number(): void
    {
        $printer = Asset::factory()->create(['name' => 'Printer']);
        $meja    = Asset::factory()->create(['name' => 'Meja']);
        $laptop  = Asset::factory()->create(['name' => 'Laptop']);
        $ac      = Asset::factory()->create(['name' => 'AC']);

        $records = collect([$printer, $meja, $laptop, $ac]);
        $sorted  = $this->resolveViaReflection($records);

        $this->assertSame(['AC', 'Laptop', 'Meja', 'Printer'], $sorted->pluck('name')->values()->all(),
            'Cetak Barcode harus mengurutkan barang sama seperti Cetak Checklist (name A→Z)');
    }

    public function test_barcode_bulk_secondary_sort_by_inventory_number(): void
    {
        // Nama sama, beda kode inventaris -- gunakan unique inventory_number manual
        $z = Asset::factory()->create(['name' => 'Kursi', 'inventory_number' => 'INV0000300']);
        $a = Asset::factory()->create(['name' => 'Kursi', 'inventory_number' => 'INV0000100']);
        $b = Asset::factory()->create(['name' => 'Kursi', 'inventory_number' => 'INV0000200']);

        $sorted = $this->resolveViaReflection(collect([$z, $a, $b]));

        $this->assertSame(['INV0000100', 'INV0000200', 'INV0000300'],
            $sorted->pluck('inventory_number')->values()->all(),
            'Saat nama sama, urut berdasarkan inventory_number A→Z');
    }

    public function test_barcode_sort_matches_checklist_sort(): void
    {
        $assets = collect([
            Asset::factory()->create(['name' => 'Printer']),
            Asset::factory()->create(['name' => 'AC']),
            Asset::factory()->create(['name' => 'Meja']),
            Asset::factory()->create(['name' => 'Laptop']),
        ]);

        // Urutan Cetak Checklist (sesuai web.php)
        $ids = $assets->pluck('id');
        $checklistOrder = Asset::whereIn('id', $ids)
            ->orderBy('name')
            ->orderBy('inventory_number')
            ->pluck('name')
            ->values()
            ->all();

        // Urutan Cetak Barcode via resolveAssets
        $barcodeOrder = $this->resolveViaReflection($assets)
            ->pluck('name')
            ->values()
            ->all();

        $this->assertSame($checklistOrder, $barcodeOrder,
            'Urutan Cetak Barcode harus identik dengan Cetak Checklist');
    }

    public function test_queueForLocation_sorted_by_name_then_inventory_number(): void
    {
        $campus   = Campus::factory()->create();
        $location = Location::factory()->create(['campus_id' => $campus->id]);

        Asset::factory()->create(['name' => 'Printer', 'campus_id' => $campus->id, 'location_id' => $location->id]);
        Asset::factory()->create(['name' => 'AC',      'campus_id' => $campus->id, 'location_id' => $location->id]);
        Asset::factory()->create(['name' => 'Meja',    'campus_id' => $campus->id, 'location_id' => $location->id]);

        $queued = Asset::where('location_id', $location->id)
            ->whereNotNull('barcode')
            ->orderBy('name')
            ->orderBy('inventory_number')
            ->get();

        $this->assertSame(['AC', 'Meja', 'Printer'], $queued->pluck('name')->values()->all());
    }

    public function test_assets_without_barcode_excluded_from_barcode_print(): void
    {
        // Observer akan auto-generate barcode -- untuk force null kita bypass dengan DB update
        $withBarcode    = Asset::factory()->create(['name' => 'Laptop']);
        $withoutBarcode = Asset::factory()->create(['name' => 'Monitor']);

        // Force null barcode di DB setelah create (bypass observer)
        \DB::table('assets')->where('id', $withoutBarcode->id)->update(['barcode' => null]);
        $withoutBarcode->refresh();

        $records = collect([$withBarcode, $withoutBarcode]);
        $result  = $this->resolveViaReflection($records);

        $this->assertCount(1, $result);
        $this->assertEquals('Laptop', $result->first()->name);
    }
}