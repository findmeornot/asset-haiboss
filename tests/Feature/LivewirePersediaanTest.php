<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\InventoryBalance;
use App\Models\Campus;
use App\Models\Location;
use Livewire\Livewire;
use App\Filament\Inventory\Resources\MutationResource\Pages\CreateMutation;

class LivewirePersediaanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_ui_filtering_persediaan()
    {
        $campusA = Campus::factory()->create();
        $roomA = Location::factory()->create(['campus_id' => $campusA->id]);
        $roomB = Location::factory()->create(['campus_id' => $campusA->id]);
        
        $cat = \App\Models\Category::factory()->create();

        $balance1 = InventoryBalance::create([
            'category_id' => $cat->id,
            'name' => 'Tinta 1',
            'campus_id' => $campusA->id,
            'location_id' => $roomA->id,
            'quantity' => 10,
        ]);
        
        $balance2 = InventoryBalance::create([
            'category_id' => $cat->id,
            'name' => 'Kertas 1',
            'campus_id' => $campusA->id,
            'location_id' => $roomA->id,
            'quantity' => 0, // Should NOT be visible!
        ]);
        
        $balance3 = InventoryBalance::create([
            'category_id' => $cat->id,
            'name' => 'Tinta 2',
            'campus_id' => $campusA->id,
            'location_id' => $roomB->id,
            'quantity' => 20, // Different room!
        ]);

        Livewire::test(CreateMutation::class)
            ->fillForm([
                'type' => 'consumable',
                'source_campus_id' => $campusA->id,
                'source_location_id' => $roomA->id,
                'items' => [
                    ['inventory_balance_id' => null, 'quantity' => null] // Simulate clicking "Add Item"
                ]
            ])
            ->assertSee($balance1->name) 
            ->assertDontSee($balance2->name) // 0 qty
            ->assertDontSee($balance3->name); // room B
    }

    public function test_ui_filtering_source_location_persediaan()
    {
        $campusA = Campus::factory()->create();
        $roomA = Location::factory()->create(['campus_id' => $campusA->id]);
        $roomB = Location::factory()->create(['campus_id' => $campusA->id]);
        
        $cat = \App\Models\Category::factory()->create();

        $balance1 = InventoryBalance::create([
            'category_id' => $cat->id,
            'name' => 'Tinta 1',
            'campus_id' => $campusA->id,
            'location_id' => $roomA->id,
            'quantity' => 10,
        ]);
        
        $balance2 = InventoryBalance::create([
            'category_id' => $cat->id,
            'name' => 'Tinta 2',
            'campus_id' => $campusA->id,
            'location_id' => $roomB->id,
            'quantity' => 20, 
        ]);

        Livewire::test(CreateMutation::class)
            ->fillForm([
                'type' => 'consumable',
                'source_campus_id' => $campusA->id,
                'source_location_id' => $roomA->id,
                'items' => [
                    ['inventory_balance_id' => null, 'quantity' => null]
                ]
            ])
            ->assertSee($balance1->name) 
            ->assertDontSee($balance2->name)
            ->fillForm(['source_location_id' => $roomB->id]) // User changes location
            ->fillForm([
                'items' => [
                    ['inventory_balance_id' => null, 'quantity' => null] // Simulate Add Item again
                ]
            ])
            ->assertSee($balance2->name)
            ->assertDontSee($balance1->name);
    }
}
