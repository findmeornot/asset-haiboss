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

class LivewireMutationTest extends TestCase
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
        
        $assetA1 = Asset::factory()->create([
            'classification_id' => $classAset->id,
            'campus_id' => $campusA->id,
            'location_id' => $roomA->id,
            'status' => 'stock'
        ]);
        
        $assetI1 = Asset::factory()->create([
            'classification_id' => $classInv->id,
            'campus_id' => $campusA->id,
            'location_id' => $roomA->id,
            'status' => 'stock'
        ]);
        
        $assetA2 = Asset::factory()->create([
            'classification_id' => $classAset->id,
            'campus_id' => $campusA->id,
            'location_id' => $roomB->id,
            'status' => 'stock'
        ]);

        Livewire::test(CreateMutation::class)
            ->set('data.type', 'asset')
            ->set('data.source_campus_id', $campusA->id)
            ->set('data.source_location_id', $roomA->id)
            ->assertSee($assetA1->name) // Tampil
            ->assertDontSee($assetI1->name) // Tidak tampil inventaris
            ->assertDontSee($assetA2->name); // Tidak tampil beda ruangan
    }
}
