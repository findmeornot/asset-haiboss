<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetStatusHistory;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_is_soft_deleted_not_force_deleted()
    {
        $asset = Asset::factory()->create();
        
        $asset->delete(); // Soft delete

        $this->assertSoftDeleted($asset);
        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
    }

    public function test_active_query_excludes_soft_deleted_assets()
    {
        $asset = Asset::factory()->create();
        $asset->delete();

        $activeAssets = Asset::all();
        $this->assertCount(0, $activeAssets);

        $allAssets = Asset::withTrashed()->get();
        $this->assertCount(1, $allAssets);
    }

    public function test_history_is_preserved_on_soft_delete()
    {
        $asset = Asset::factory()->create(['status' => 'stock']);
        
        $history = AssetStatusHistory::create([
            'asset_id' => $asset->id,
            'old_status' => 'new',
            'new_status' => 'stock',
            'changed_by' => User::factory()->create()->id,
        ]);

        $asset->delete(); // Soft delete

        $this->assertDatabaseHas('asset_status_histories', ['id' => $history->id]);
        $this->assertSoftDeleted($asset);
    }

    public function test_force_delete_fails_if_history_exists_due_to_restrict()
    {
        $asset = Asset::factory()->create(['status' => 'stock']);
        
        AssetStatusHistory::create([
            'asset_id' => $asset->id,
            'old_status' => 'new',
            'new_status' => 'stock',
            'changed_by' => User::factory()->create()->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->expectExceptionMessageMatches('/foreign key constraint fails/');

        // Force delete should fail because of RESTRICT foreign key
        $asset->forceDelete();
    }

    public function test_restore_brings_asset_back_with_same_inventory_number()
    {
        $asset = Asset::factory()->create(['inventory_number' => 'INV-00123']);
        $asset->delete();

        $this->assertSoftDeleted($asset);

        $asset->restore();

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'inventory_number' => 'INV-00123',
            'deleted_at' => null,
        ]);
    }
}
