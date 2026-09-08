<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Asset;
use App\Models\Campus;
use App\Models\Location;
use App\Models\Classification;
use Livewire\Livewire;
use App\Filament\Inventory\Resources\MutationResource\Pages\CreateMutation;

class LivewireCrossTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_cross_classification_submission_rejected()
    {
        $classAset = Classification::create(['name' => 'Aset', 'slug' => 'aset']);
        $campusA = Campus::factory()->create();
        $roomA = Location::factory()->create(['campus_id' => $campusA->id]);
        $destCampus = Campus::factory()->create();
        $destRoom = Location::factory()->create(['campus_id' => $destCampus->id]);
        
        $assetA1 = Asset::factory()->create(['classification_id' => $classAset->id, 'campus_id' => $campusA->id, 'location_id' => $roomA->id, 'status' => 'stock']);
        
        Livewire::test(CreateMutation::class)
            ->fillForm([
                'type' => 'asset',
                'source_campus_id' => $campusA->id,
                'source_location_id' => $roomA->id,
                'asset_ids' => [$assetA1->id],
                'destination_campus_id' => $destCampus->id,
                'destination_location_id' => $destRoom->id,
            ])
            ->fillForm(['type' => 'inventory']) // User changes type directly! This triggers cascading reset!
            ->call('create') // Attempt to save
            ->assertHasFormErrors(['asset_ids' => 'required']); // Filament requires at least 1 item
    }

    public function test_cross_location_submission_rejected()
    {
        $classAset = Classification::create(['name' => 'Aset', 'slug' => 'aset']);
        $campusA = Campus::factory()->create();
        $roomA = Location::factory()->create(['campus_id' => $campusA->id]);
        $roomB = Location::factory()->create(['campus_id' => $campusA->id]);
        $destCampus = Campus::factory()->create();
        $destRoom = Location::factory()->create(['campus_id' => $destCampus->id]);
        
        $assetA1 = Asset::factory()->create(['classification_id' => $classAset->id, 'campus_id' => $campusA->id, 'location_id' => $roomA->id, 'status' => 'stock']);
        
        Livewire::test(CreateMutation::class)
            ->fillForm([
                'type' => 'asset',
                'source_campus_id' => $campusA->id,
                'source_location_id' => $roomA->id,
                'asset_ids' => [$assetA1->id],
                'destination_campus_id' => $destCampus->id,
                'destination_location_id' => $destRoom->id,
            ])
            ->fillForm(['source_location_id' => $roomB->id]) // User changes location!
            ->call('create') // Attempt to save
            ->assertHasFormErrors(['asset_ids' => 'required']);
    }
}
