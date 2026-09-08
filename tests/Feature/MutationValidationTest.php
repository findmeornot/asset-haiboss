<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Asset;
use App\Models\InventoryBalance;
use App\Models\Campus;
use App\Models\Location;
use App\Models\Classification;
use App\Models\Category;
use Illuminate\Validation\ValidationException;
use App\Services\MutationValidationService;

class MutationValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_wrong_classification_rejected()
    {
        $classInventaris = Classification::create(['name' => 'Inventaris', 'slug' => 'inventaris']);
        $campus = Campus::factory()->create();
        $location = Location::factory()->create(['campus_id' => $campus->id]);
        
        $asset = Asset::factory()->create([
            'classification_id' => $classInventaris->id,
            'campus_id' => $campus->id,
            'location_id' => $location->id,
            'status' => 'stock'
        ]);

        $this->expectException(ValidationException::class);
        // Type asset but classification inventaris should fail
        MutationValidationService::validateAndLockItems('asset', [$asset->id], $campus->id, $location->id);
    }

    public function test_wrong_location_rejected()
    {
        $classAsset = Classification::create(['name' => 'Aset', 'slug' => 'aset']);
        $campus = Campus::factory()->create();
        $campus2 = Campus::factory()->create();
        $location = Location::factory()->create(['campus_id' => $campus->id]);
        $location2 = Location::factory()->create(['campus_id' => $campus2->id]);
        
        $asset = Asset::factory()->create([
            'classification_id' => $classAsset->id,
            'campus_id' => $campus2->id,
            'location_id' => $location2->id,
            'status' => 'stock'
        ]);

        $this->expectException(ValidationException::class);
        MutationValidationService::validateAndLockItems('asset', [$asset->id], $campus->id, $location->id);
    }

    public function test_invalid_status_rejected()
    {
        $classAsset = Classification::create(['name' => 'Aset', 'slug' => 'aset']);
        $campus = Campus::factory()->create();
        $location = Location::factory()->create(['campus_id' => $campus->id]);
        
        $asset = Asset::factory()->create([
            'classification_id' => $classAsset->id,
            'campus_id' => $campus->id,
            'location_id' => $location->id,
            'status' => 'lost' // Not in whitelist ('stock', 'active')
        ]);

        $this->expectException(ValidationException::class);
        MutationValidationService::validateAndLockItems('asset', [$asset->id], $campus->id, $location->id);
    }

    public function test_valid_status_accepted()
    {
        $classAsset = Classification::create(['name' => 'Aset', 'slug' => 'aset']);
        $campus = Campus::factory()->create();
        $location = Location::factory()->create(['campus_id' => $campus->id]);
        
        $asset = Asset::factory()->create([
            'classification_id' => $classAsset->id,
            'campus_id' => $campus->id,
            'location_id' => $location->id,
            'status' => 'active' 
        ]);

        // Should not throw exception
        MutationValidationService::validateAndLockItems('asset', [$asset->id], $campus->id, $location->id);
        $this->assertTrue(true);
    }

    public function test_persediaan_insufficient_stock_rejected()
    {
        $campus = Campus::factory()->create();
        $location = Location::factory()->create(['campus_id' => $campus->id]);
        $category = Category::factory()->create();
        
        $balance = InventoryBalance::create([
            'category_id' => $category->id,
            'name' => 'Tinta',
            'campus_id' => $campus->id,
            'location_id' => $location->id,
            'quantity' => 20,
        ]);

        $this->expectException(ValidationException::class);
        MutationValidationService::validateAndLockItems('consumable', [
            ['inventory_balance_id' => $balance->id, 'quantity' => 30]
        ], $campus->id, $location->id);
    }

    public function test_persediaan_zero_quantity_rejected()
    {
        $campus = Campus::factory()->create();
        $location = Location::factory()->create(['campus_id' => $campus->id]);
        $category = Category::factory()->create();
        
        $balance = InventoryBalance::create([
            'category_id' => $category->id,
            'name' => 'Tinta',
            'campus_id' => $campus->id,
            'location_id' => $location->id,
            'quantity' => 20,
        ]);

        $this->expectException(ValidationException::class);
        MutationValidationService::validateAndLockItems('consumable', [
            ['inventory_balance_id' => $balance->id, 'quantity' => 0]
        ], $campus->id, $location->id);
    }

    public function test_stale_data_rejected()
    {
        $classAsset = Classification::create(['name' => 'Aset', 'slug' => 'aset']);
        $campus = Campus::factory()->create();
        $locationA = Location::factory()->create(['campus_id' => $campus->id]);
        $locationB = Location::factory()->create(['campus_id' => $campus->id]);
        
        $asset = Asset::factory()->create([
            'classification_id' => $classAsset->id,
            'campus_id' => $campus->id,
            'location_id' => $locationA->id,
            'status' => 'stock'
        ]);

        // Simulate asset being moved externally to Location B BEFORE validation
        $asset->update(['location_id' => $locationB->id]);

        $this->expectException(ValidationException::class);
        // Form submitted with source location A
        MutationValidationService::validateAndLockItems('asset', [$asset->id], $campus->id, $locationA->id);
    }
}
