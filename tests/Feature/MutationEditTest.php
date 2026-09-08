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
use App\Models\Classification;
use App\Models\Mutation;
use App\Models\MutationItem;

class MutationEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Buat user (bila perlu untuk testing API/UI)
        $this->user = User::factory()->create();
    }

    public function test_edit_wrong_classification_rejected()
    {
        $classificationAset = Classification::create(['name' => 'Aset', 'slug' => 'aset']);
        $classificationInv = Classification::create(['name' => 'Inventaris', 'slug' => 'inventaris']);
        
        $campus = Campus::factory()->create();
        $location = Location::factory()->create(['campus_id' => $campus->id]);
        
        $asset = Asset::factory()->create([
            'classification_id' => $classificationInv->id, // salah classification
            'campus_id' => $campus->id,
            'location_id' => $location->id,
            'status' => 'active'
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        \App\Services\MutationValidationService::validateAndLockItems('asset', [$asset->id], $campus->id, $location->id);
    }

    public function test_edit_wrong_location_rejected()
    {
        $classificationAset = Classification::create(['name' => 'Aset', 'slug' => 'aset']);
        
        $campus = Campus::factory()->create();
        $location1 = Location::factory()->create(['campus_id' => $campus->id]);
        $location2 = Location::factory()->create(['campus_id' => $campus->id]); // Lokasi berbeda
        
        $asset = Asset::factory()->create([
            'classification_id' => $classificationAset->id,
            'campus_id' => $campus->id,
            'location_id' => $location2->id,
            'status' => 'active'
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        \App\Services\MutationValidationService::validateAndLockItems('asset', [$asset->id], $campus->id, $location1->id);
    }
    
    public function test_edit_invalid_status_rejected()
    {
        $classificationAset = Classification::create(['name' => 'Aset', 'slug' => 'aset']);
        
        $campus = Campus::factory()->create();
        $location = Location::factory()->create(['campus_id' => $campus->id]);
        
        $asset = Asset::factory()->create([
            'classification_id' => $classificationAset->id,
            'campus_id' => $campus->id,
            'location_id' => $location->id,
            'status' => 'lost' // status tidak eligible
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        \App\Services\MutationValidationService::validateAndLockItems('asset', [$asset->id], $campus->id, $location->id);
    }
    
    public function test_edit_insufficient_stock_rejected()
    {
        $campus = Campus::factory()->create();
        $location = Location::factory()->create(['campus_id' => $campus->id]);
        
        $category = Category::factory()->create();

        $balance = InventoryBalance::create([
            'category_id' => $category->id,
            'name' => 'Kertas A4',
            'campus_id' => $campus->id,
            'location_id' => $location->id,
            'quantity' => 20,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        \App\Services\MutationValidationService::validateAndLockItems('consumable', [
            ['inventory_balance_id' => $balance->id, 'quantity' => 50]
        ], $campus->id, $location->id);
    }
}
