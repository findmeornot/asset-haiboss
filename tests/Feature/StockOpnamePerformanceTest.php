<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Campus;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockOpnamePerformanceTest extends TestCase
{
    use DatabaseTruncation;

    public function test_bulk_creation_reduces_queries()
    {
        $campus = Campus::factory()->create();
        $location = \App\Models\Location::factory()->create(['campus_id' => $campus->id]);
        
        // Generate 1000 assets for the campus
        Asset::factory()->count(1000)->create([
            'campus_id' => $campus->id,
            'location_id' => $location->id,
            'status' => 'stock'
        ]);

        $stockOpname = StockOpname::factory()->create([
            'campus_id' => $campus->id,
            'status' => 'draft',
        ]);

        DB::enableQueryLog();

        // Simulate Action Start
        DB::transaction(function () use ($stockOpname) {
            $lockedRecord = StockOpname::where('id', $stockOpname->id)->lockForUpdate()->first();
            $lockedRecord->update(['status' => 'in_progress', 'start_date' => now()]);
            
            $now = now();
            Asset::where('campus_id', $lockedRecord->campus_id)->chunkById(500, function ($assets) use ($lockedRecord, $now) {
                $items = [];
                foreach ($assets as $asset) {
                    $items[] = [
                        'stock_opname_id' => $lockedRecord->id,
                        'asset_id' => $asset->id,
                        'expected_location_id' => $asset->location_id,
                        'is_found' => false,
                        'condition' => $asset->status,
                        'location_id' => null,
                        'actual_location_id' => null,
                        'scanned_inventory_number' => null,
                        'notes' => null,
                        'checked_by' => null,
                        'checked_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                StockOpnameItem::insertOrIgnore($items);
            });
        });

        $queryLog = DB::getQueryLog();
        
        $this->assertCount(1000, StockOpnameItem::where('stock_opname_id', $stockOpname->id)->get());
        
        // 1 (lock) + 1 (update) + (1000/500 = 2 selects) + 2 inserts + 1 count = 7 queries expected.
        // It should be extremely low compared to 1000+ queries.
        $this->assertLessThan(20, count($queryLog));
    }

    public function test_unique_constraint_prevents_duplicate_items()
    {
        $campus = Campus::factory()->create();
        $location = \App\Models\Location::factory()->create(['campus_id' => $campus->id]);
        $asset = Asset::factory()->create(['campus_id' => $campus->id, 'location_id' => $location->id]);
        $opname = StockOpname::factory()->create(['campus_id' => $campus->id]);

        StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'asset_id' => $asset->id
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->expectExceptionMessageMatches('/Duplicate entry/');

        StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'asset_id' => $asset->id
        ]);
    }
}
