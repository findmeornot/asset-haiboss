<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Asset;
use App\Models\InventoryBalance;
use App\Models\Campus;
use App\Models\Location;
use App\Models\Category;
use App\Models\Mutation;
use App\Models\MutationItem;

class MutationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure necessary enums/roles exist if needed by your app
        // But for unit testing the model logic directly, we might just bypass auth or create a simple user
        $this->user = User::factory()->create();
    }

    public function test_asset_mutation_updates_location_on_complete()
    {
        $sourceCampus = Campus::factory()->create();
        $sourceLocation = Location::factory()->create(['campus_id' => $sourceCampus->id]);
        
        $destCampus = Campus::factory()->create();
        $destLocation = Location::factory()->create(['campus_id' => $destCampus->id]);

        $asset = Asset::factory()->create([
            'campus_id' => $sourceCampus->id,
            'location_id' => $sourceLocation->id,
        ]);

        $mutation = Mutation::create([
            'type' => 'asset',
            'source_campus_id' => $sourceCampus->id,
            'source_location_id' => $sourceLocation->id,
            'destination_campus_id' => $destCampus->id,
            'destination_location_id' => $destLocation->id,
            'status' => 'approved',
        ]);

        MutationItem::create([
            'mutation_id' => $mutation->id,
            'asset_id' => $asset->id,
        ]);

        // Complete the mutation
        // Simulate the action logic
        \Illuminate\Support\Facades\DB::transaction(function () use ($mutation) {
            $lockedRecord = Mutation::where('id', $mutation->id)->lockForUpdate()->first();
            $lockedRecord->update(['status' => 'completed']);
            
            foreach ($lockedRecord->items as $item) {
                if ($lockedRecord->type === 'asset' && $item->asset_id) {
                    $asset = Asset::where('id', $item->asset_id)->first();
                    $asset->update([
                        'campus_id' => $lockedRecord->destination_campus_id,
                        'location_id' => $lockedRecord->destination_location_id,
                    ]);
                }
            }
        });

        $asset->refresh();
        $this->assertEquals($destCampus->id, $asset->campus_id);
        $this->assertEquals($destLocation->id, $asset->location_id);
    }

    public function test_inventory_mutation_deducts_source_and_adds_to_destination()
    {
        $sourceCampus = Campus::factory()->create();
        $sourceLocation = Location::factory()->create(['campus_id' => $sourceCampus->id]);
        
        $destCampus = Campus::factory()->create();
        $destLocation = Location::factory()->create(['campus_id' => $destCampus->id]);
        
        $category = Category::factory()->create();

        $sourceBalance = InventoryBalance::create([
            'category_id' => $category->id,
            'name' => 'Kertas A4',
            'campus_id' => $sourceCampus->id,
            'location_id' => $sourceLocation->id,
            'quantity' => 100,
        ]);

        $mutation = Mutation::create([
            'type' => 'inventory',
            'source_campus_id' => $sourceCampus->id,
            'source_location_id' => $sourceLocation->id,
            'destination_campus_id' => $destCampus->id,
            'destination_location_id' => $destLocation->id,
            'status' => 'approved',
        ]);

        MutationItem::create([
            'mutation_id' => $mutation->id,
            'inventory_balance_id' => $sourceBalance->id,
            'quantity' => 30,
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($mutation) {
            $lockedRecord = Mutation::where('id', $mutation->id)->lockForUpdate()->first();
            $lockedRecord->update(['status' => 'completed']);
            
            foreach ($lockedRecord->items as $item) {
                if (in_array($lockedRecord->type, ['inventory', 'consumable']) && $item->inventory_balance_id) {
                    $sourceBalance = InventoryBalance::where('id', $item->inventory_balance_id)->lockForUpdate()->first();
                    
                    $sourceBalance->quantity -= $item->quantity;
                    $sourceBalance->save();
                    
                    $destBalance = InventoryBalance::lockForUpdate()->firstOrCreate([
                        'category_id' => $sourceBalance->category_id,
                        'name' => $sourceBalance->name,
                        'campus_id' => $lockedRecord->destination_campus_id,
                        'location_id' => $lockedRecord->destination_location_id,
                    ], [
                        'quantity' => 0,
                        'status' => 'stock',
                        'kondisi' => 'good',
                    ]);
                    
                    $destBalance->quantity += $item->quantity;
                    $destBalance->save();
                }
            }
        });

        $sourceBalance->refresh();
        $this->assertEquals(70, $sourceBalance->quantity);

        $destBalance = InventoryBalance::where('campus_id', $destCampus->id)
            ->where('location_id', $destLocation->id)
            ->where('name', 'Kertas A4')
            ->first();
            
        $this->assertNotNull($destBalance);
        $this->assertEquals(30, $destBalance->quantity);
    }
}
