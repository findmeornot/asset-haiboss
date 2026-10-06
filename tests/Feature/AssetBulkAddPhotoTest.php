<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetPhoto;
use App\Models\User;
use App\Filament\Inventory\Resources\AssetResource\Pages\ListAssets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AssetBulkAddPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $role = \App\Models\Role::firstOrCreate(['name' => 'superadmin']);
        
        $user = User::factory()->create();
        $user->roles()->attach([$role->id]);
        $this->actingAs($user);
        
        Storage::fake('s3');
    }

    public function test_bulk_add_photo_appends_to_existing_photos()
    {
        $asset1 = Asset::factory()->create();
        $asset2 = Asset::factory()->create();

        // Asset 1 has 1 photo already
        $asset1->photos()->create(['file_path' => 'old1.jpg', 'sort_order' => 0]);
        // Asset 2 has no photos

        $file1 = 'new_photo_1.jpg';
        Storage::disk('s3')->put($file1, 'content');

        Livewire::test(ListAssets::class)
            ->callTableBulkAction('addPhotoBulk', [
                $asset1->id,
                $asset2->id,
            ], data: [
                'new_photos' => [$file1],
                'allow_overwrite' => false,
            ])
            ->assertSuccessful();

        $this->assertEquals(2, $asset1->photos()->count());
        $this->assertEquals(1, $asset2->photos()->count());

        $this->assertDatabaseHas('asset_photos', ['asset_id' => $asset1->id, 'file_path' => 'new_photo_1.jpg', 'sort_order' => 1]);
        $this->assertDatabaseHas('asset_photos', ['asset_id' => $asset2->id, 'file_path' => 'new_photo_1.jpg', 'sort_order' => 0]);
    }
}