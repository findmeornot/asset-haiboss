<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Asset;
use App\Models\InventoryBalance;
use App\Models\Campus;
use App\Models\Location;
use App\Models\Classification;
use Livewire\Livewire;
use App\Filament\Inventory\Resources\MutationResource\Pages\CreateMutation;

class LivewireMutationFullTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_ui_filtering_asset()
    {
        $classAset = Classification::create(['name' => 'Aset', 'slug' => 'aset']);
        $classInv = Classification::create(['name' => 'Inventaris', 'slug' => 'inventaris']);
        
        $campusA = Campus::factory()->create();
        $roomA = Location::factory()->create(['campus_id' => $campusA->id]);
        $roomB = Location::factory()->create(['campus_id' => $campusA->id]);
        
        $assetA1 = Asset::factory()->create(['classification_id' => $classAset->id, 'campus_id' => $campusA->id, 'location_id' => $roomA->id, 'status' => 'stock']);
        $assetI1 = Asset::factory()->create(['classification_id' => $classInv->id, 'campus_id' => $campusA->id, 'location_id' => $roomA->id, 'status' => 'stock']);
        $assetA2 = Asset::factory()->create(['classification_id' => $classAset->id, 'campus_id' => $campusA->id, 'location_id' => $roomB->id, 'status' => 'stock']);

        Livewire::test(CreateMutation::class)
            ->fillForm([
                'type' => 'asset',
                'source_campus_id' => $campusA->id,
                'source_location_id' => $roomA->id,
            ])
            ->assertSee($assetA1->name) // Tampil
            ->assertDontSee($assetI1->name) // Tidak tampil inventaris
            ->assertDontSee($assetA2->name); // Tidak tampil beda ruangan
    }

    public function test_ui_filtering_inventaris()
    {
        $classAset = Classification::create(['name' => 'Aset', 'slug' => 'aset']);
        $classInv = Classification::create(['name' => 'Inventaris', 'slug' => 'inventaris']);
        
        $campusA = Campus::factory()->create();
        $roomA = Location::factory()->create(['campus_id' => $campusA->id]);
        $roomB = Location::factory()->create(['campus_id' => $campusA->id]);
        
        $assetA1 = Asset::factory()->create(['classification_id' => $classAset->id, 'campus_id' => $campusA->id, 'location_id' => $roomA->id, 'status' => 'stock']);
        $assetI1 = Asset::factory()->create(['classification_id' => $classInv->id, 'campus_id' => $campusA->id, 'location_id' => $roomA->id, 'status' => 'stock']);
        $assetA2 = Asset::factory()->create(['classification_id' => $classAset->id, 'campus_id' => $campusA->id, 'location_id' => $roomB->id, 'status' => 'stock']);

        Livewire::test(CreateMutation::class)
            ->fillForm([
                'type' => 'inventory',
                'source_campus_id' => $campusA->id,
                'source_location_id' => $roomA->id,
            ])
            ->assertSee($assetI1->name) 
            ->assertDontSee($assetA1->name) 
            ->assertDontSee($assetA2->name); 
    }

    public function test_ui_filtering_source_location()
    {
        $classAset = Classification::create(['name' => 'Aset', 'slug' => 'aset']);
        
        $campusA = Campus::factory()->create();
        $roomA = Location::factory()->create(['campus_id' => $campusA->id]);
        $roomB = Location::factory()->create(['campus_id' => $campusA->id]);
        
        $assetA1 = Asset::factory()->create(['classification_id' => $classAset->id, 'campus_id' => $campusA->id, 'location_id' => $roomA->id, 'status' => 'stock']);
        $assetA2 = Asset::factory()->create(['classification_id' => $classAset->id, 'campus_id' => $campusA->id, 'location_id' => $roomB->id, 'status' => 'stock']);

        Livewire::test(CreateMutation::class)
            ->fillForm([
                'type' => 'asset',
                'source_campus_id' => $campusA->id,
                'source_location_id' => $roomA->id,
            ])
            ->assertSee($assetA1->name) 
            ->assertDontSee($assetA2->name)
            ->fillForm(['source_location_id' => $roomB->id])
            ->assertSee($assetA2->name)
            ->assertDontSee($assetA1->name);
    }

    public function test_ghost_state_change_type()
    {
        $classAset = Classification::create(['name' => 'Aset', 'slug' => 'aset']);
        $campusA = Campus::factory()->create();
        $roomA = Location::factory()->create(['campus_id' => $campusA->id]);
        
        $assetA1 = Asset::factory()->create(['classification_id' => $classAset->id, 'campus_id' => $campusA->id, 'location_id' => $roomA->id, 'status' => 'stock']);
        
        Livewire::test(CreateMutation::class)
            ->fillForm([
                'type' => 'asset',
                'source_campus_id' => $campusA->id,
                'source_location_id' => $roomA->id,
                'asset_ids' => [$assetA1->id]
            ])
            ->assertFormSet(['asset_ids' => [$assetA1->id]]) // Check it's set
            ->fillForm(['type' => 'inventory']) // User changes type
            ->assertFormSet(['asset_ids' => []]); // Should be reset to empty!
    }

    public function test_ghost_state_change_campus()
    {
        $classAset = Classification::create(['name' => 'Aset', 'slug' => 'aset']);
        $campusA = Campus::factory()->create();
        $campusB = Campus::factory()->create();
        $roomA = Location::factory()->create(['campus_id' => $campusA->id]);
        
        $assetA1 = Asset::factory()->create(['classification_id' => $classAset->id, 'campus_id' => $campusA->id, 'location_id' => $roomA->id, 'status' => 'stock']);
        
        Livewire::test(CreateMutation::class)
            ->fillForm([
                'type' => 'asset',
                'source_campus_id' => $campusA->id,
                'source_location_id' => $roomA->id,
                'asset_ids' => [$assetA1->id]
            ])
            ->fillForm(['source_campus_id' => $campusB->id]) // User changes campus
            ->assertFormSet(['asset_ids' => []])
            ->assertFormSet(['source_location_id' => null]); 
    }
    
    public function test_ghost_state_change_location()
    {
        $classAset = Classification::create(['name' => 'Aset', 'slug' => 'aset']);
        $campusA = Campus::factory()->create();
        $roomA = Location::factory()->create(['campus_id' => $campusA->id]);
        $roomB = Location::factory()->create(['campus_id' => $campusA->id]);
        
        $assetA1 = Asset::factory()->create(['classification_id' => $classAset->id, 'campus_id' => $campusA->id, 'location_id' => $roomA->id, 'status' => 'stock']);
        
        Livewire::test(CreateMutation::class)
            ->fillForm([
                'type' => 'asset',
                'source_campus_id' => $campusA->id,
                'source_location_id' => $roomA->id,
                'asset_ids' => [$assetA1->id]
            ])
            ->fillForm(['source_location_id' => $roomB->id]) // User changes room
            ->assertFormSet(['asset_ids' => []]); 
    }

    public function test_ghost_state_persediaan()
    {
        $campusA = Campus::factory()->create();
        $roomA = Location::factory()->create(['campus_id' => $campusA->id]);
        $roomB = Location::factory()->create(['campus_id' => $campusA->id]);
        
        $balance = InventoryBalance::create([
            'category_id' => \App\Models\Category::factory()->create()->id,
            'name' => 'Tinta',
            'campus_id' => $campusA->id,
            'location_id' => $roomA->id,
            'quantity' => 10,
        ]);

        Livewire::test(CreateMutation::class)
            ->fillForm([
                'type' => 'consumable',
                'source_campus_id' => $campusA->id,
                'source_location_id' => $roomA->id,
                'items' => [
                    ['inventory_balance_id' => $balance->id, 'quantity' => 5]
                ]
            ])
            ->fillForm(['source_location_id' => $roomB->id]) // User changes room
            ->assertFormSet(['items' => []]) // Should be reset
            ->fillForm([
                'items' => [
                    ['inventory_balance_id' => null, 'quantity' => null]
                ]
            ])
            ->assertDontSee('Tinta'); // Balance was in Room A, should not be visible in Room B!
    }
}
