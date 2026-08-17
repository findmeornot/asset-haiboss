<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Campus;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use App\Services\BarcodeService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

class BarcodeSystemTest extends TestCase
{
    use DatabaseTruncation;

    public function test_barcode_generation()
    {
        $service = new BarcodeService();
        $svg = $service->generateSvg('INV-000001');

        $this->assertStringContainsString('<svg', $svg);
        // Ensure same output for same input
        $svg2 = $service->generateSvg('INV-000001');
        $this->assertEquals($svg, $svg2);

        // Different output for different input
        $svg3 = $service->generateSvg('INV-000002');
        $this->assertNotEquals($svg, $svg3);
    }

    public function test_barcode_lookup()
    {
        $asset = Asset::factory()->create(['inventory_number' => 'INV-000001']);

        // Since the scanner uses a Livewire/Filament component internally, we test the logic via the model query
        $scannedAsset = Asset::where('inventory_number', 'INV-000001')->first();
        $this->assertNotNull($scannedAsset);
        $this->assertEquals($asset->id, $scannedAsset->id);
    }

    public function test_unknown_barcode()
    {
        $scannedAsset = Asset::where('inventory_number', 'UNKNOWN-999')->first();
        $this->assertNull($scannedAsset);
    }

    public function test_asset_outside_stock_opname()
    {
        $campus = Campus::factory()->create();
        $location = \App\Models\Location::factory()->create(['campus_id' => $campus->id]);
        $asset1 = Asset::factory()->create(['campus_id' => $campus->id, 'location_id' => $location->id, 'inventory_number' => 'INV-001']);
        $asset2 = Asset::factory()->create(['campus_id' => $campus->id, 'location_id' => $location->id, 'inventory_number' => 'INV-002']);

        $opname = StockOpname::factory()->create(['status' => 'in_progress', 'campus_id' => $campus->id]);
        
        // Only asset1 is in opname
        StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'asset_id' => $asset1->id,
            'is_found' => false,
        ]);

        $isPart1 = StockOpnameItem::where('stock_opname_id', $opname->id)
            ->where('asset_id', $asset1->id)
            ->exists();
        $this->assertTrue($isPart1);

        $isPart2 = StockOpnameItem::where('stock_opname_id', $opname->id)
            ->where('asset_id', $asset2->id)
            ->exists();
        $this->assertFalse($isPart2);
    }
}
