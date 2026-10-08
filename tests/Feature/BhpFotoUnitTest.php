<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetPhoto;
use App\Models\Classification;
use App\Models\Category;
use App\Models\Campus;
use App\Models\Location;
use App\Models\User;
use App\Models\Role;
use App\Filament\Support\AddPhotoActions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Test unit-level untuk foto BHP — tanpa rendering Livewire/Filament.
 * Mengetes logika model, relasi, storage, dan resolveAssets().
 */
class BhpFotoUnitTest extends TestCase
{
    use RefreshDatabase;

    private Classification $bhpClassification;
    private Category $bhpCategory;
    private Campus $campus;
    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'superadmin']);
        $user = User::factory()->create();
        $user->roles()->attach([$role->id]);
        Storage::fake('s3');
        Storage::fake('public');
        Storage::fake('local');
        config(['filesystems.default' => 's3']);

        $this->bhpClassification = Classification::factory()->create([
            'name' => 'Barang Habis Pakai',
            'slug' => 'barang-habis-pakai',
        ]);

        $this->campus   = Campus::factory()->create();
        $this->location = Location::factory()->create(['campus_id' => $this->campus->id]);

        $this->bhpCategory = Category::factory()->create();
        $this->bhpCategory->classifications()->attach($this->bhpClassification->id);
    }

    private function makeBhpAsset(array $overrides = []): Asset
    {
        return Asset::create(array_merge([
            'classification_id' => $this->bhpClassification->id,
            'category_id'       => $this->bhpCategory->id,
            'campus_id'         => $this->campus->id,
            'location_id'       => $this->location->id,
            'name'              => 'Tinta Printer BHP',
            'status'            => 'stock',
            'ownership'         => 'company',
        ], $overrides));
    }

    // -----------------------------------------------------------------------
    // 1. Relasi dan persistensi foto BHP
    // -----------------------------------------------------------------------

    public function test_bhp_asset_can_have_photos(): void
    {
        $bhp = $this->makeBhpAsset();

        $this->assertCount(0, $bhp->photos);

        $bhp->photos()->create(['file_path' => 'asset-photos/bhp-test.jpg', 'sort_order' => 0]);

        $this->assertCount(1, $bhp->fresh()->photos);
        $this->assertDatabaseHas('asset_photos', [
            'asset_id'  => $bhp->id,
            'file_path' => 'asset-photos/bhp-test.jpg',
        ]);
    }

    public function test_bhp_photo_persists_after_reload(): void
    {
        $bhp = $this->makeBhpAsset();
        $bhp->photos()->create(['file_path' => 'asset-photos/persist.jpg', 'sort_order' => 0]);

        $reloaded = Asset::find($bhp->id);
        $this->assertCount(1, $reloaded->photos);
        $this->assertEquals('asset-photos/persist.jpg', $reloaded->photos->first()->file_path);
    }

    public function test_bhp_can_have_up_to_3_photos(): void
    {
        $bhp = $this->makeBhpAsset();

        for ($i = 0; $i < 3; $i++) {
            $bhp->photos()->create(['file_path' => "asset-photos/bhp-{$i}.jpg", 'sort_order' => $i]);
        }

        $this->assertCount(3, $bhp->fresh()->photos);
    }

    // -----------------------------------------------------------------------
    // 2. Hapus foto BHP
    // -----------------------------------------------------------------------

    public function test_deleting_bhp_photo_removes_db_record(): void
    {
        $bhp   = $this->makeBhpAsset();
        $photo = $bhp->photos()->create(['file_path' => 'asset-photos/del.jpg', 'sort_order' => 0]);

        $photo->delete();

        $this->assertDatabaseMissing('asset_photos', ['id' => $photo->id]);
    }

    public function test_deleting_bhp_photo_removes_file_from_storage(): void
    {
        Storage::disk('s3')->put('asset-photos/del-file.jpg', 'content');

        $bhp   = $this->makeBhpAsset();
        $photo = $bhp->photos()->create([
            'file_path'  => 'asset-photos/del-file.jpg',
            'sort_order' => 0,
        ]);

        $photo->delete();

        Storage::disk('s3')->assertMissing('asset-photos/del-file.jpg');
    }

    // -----------------------------------------------------------------------
    // 3. URL foto BHP
    // -----------------------------------------------------------------------

    public function test_bhp_photo_url_returns_s3_url(): void
    {
        Storage::disk('s3')->put('asset-photos/url.jpg', 'content');

        $bhp   = $this->makeBhpAsset();
        $photo = $bhp->photos()->create(['file_path' => 'asset-photos/url.jpg', 'sort_order' => 0]);

        $this->assertNotNull($photo->url);
        $this->assertStringContainsString('url.jpg', $photo->url);
    }

    // -----------------------------------------------------------------------
    // 4. resolveAssets() — BHP sebagai Asset diproses
    // -----------------------------------------------------------------------

    public function test_resolve_assets_processes_bhp_asset_instances(): void
    {
        $bhp1 = $this->makeBhpAsset(['name' => 'BHP 1']);
        $bhp2 = $this->makeBhpAsset(['name' => 'BHP 2']);

        $collection = collect([$bhp1, $bhp2]);

        // Panggil resolveAssets via reflection (protected method)
        $resolved = $this->callProtectedResolveAssets($collection);

        $this->assertCount(2, $resolved);
        $this->assertTrue($resolved->contains('id', $bhp1->id));
        $this->assertTrue($resolved->contains('id', $bhp2->id));
    }

    public function test_resolve_assets_returns_empty_for_inventory_balance(): void
    {
        $balance = new \App\Models\InventoryBalance();
        $collection = collect([$balance]);

        $resolved = $this->callProtectedResolveAssets($collection);

        $this->assertCount(0, $resolved);
    }

    // -----------------------------------------------------------------------
    // 5. sort_order foto BHP
    // -----------------------------------------------------------------------

    public function test_bhp_photos_sorted_by_sort_order(): void
    {
        $bhp = $this->makeBhpAsset();
        $bhp->photos()->create(['file_path' => 'b.jpg', 'sort_order' => 1]);
        $bhp->photos()->create(['file_path' => 'a.jpg', 'sort_order' => 0]);

        $sorted = $bhp->fresh()->photos->sortBy('sort_order');

        $this->assertEquals('a.jpg', $sorted->first()->file_path);
        $this->assertEquals('b.jpg', $sorted->last()->file_path);
    }


    // Helpers
    // -----------------------------------------------------------------------

    /** Panggil protected static resolveAssets() via reflection. */
    private function callProtectedResolveAssets(Collection $records): Collection
    {
        $reflection = new \ReflectionClass(AddPhotoActions::class);
        $method     = $reflection->getMethod('resolveAssets');
        $method->setAccessible(true);

        return $method->invoke(null, $records);
    }
}
