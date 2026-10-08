<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetPhoto;
use App\Models\Classification;
use App\Models\Category;
use App\Models\Campus;
use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use App\Filament\Inventory\Resources\SupplyCategoryResource\Pages\ListSupplyCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Test foto untuk Barang Habis Pakai (BHP).
 *
 * BHP dikelola via SupplyCategoryResource yang menggunakan model Asset
 * dengan klasifikasi slug 'barang-habis-pakai'. Mekanisme foto sama
 * persis dengan Aset/Inventaris: tabel asset_photos, disk S3, relasi
 * Asset::photos(), dan AddPhotoActions::bulkAction().
 */
class BhpFotoTest extends TestCase
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
        $this->actingAs($user);

        Storage::fake('s3');
        config(['filesystems.default' => 's3']);

        // Buat klasifikasi BHP dengan slug yang sesuai
        $this->bhpClassification = Classification::factory()->create([
            'name' => 'Barang Habis Pakai',
            'slug' => 'barang-habis-pakai',
        ]);

        $this->campus = Campus::factory()->create();
        $this->location = Location::factory()->create(['campus_id' => $this->campus->id]);

        $this->bhpCategory = Category::factory()->create();
        $this->bhpCategory->classifications()->attach($this->bhpClassification->id);
    }

    /** Helper: buat Asset BHP (klasifikasi barang-habis-pakai). */
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
    // 1. Relasi foto
    // -----------------------------------------------------------------------

    /** BHP Asset memiliki relasi photos() yang berfungsi. */
    public function test_bhp_asset_has_photos_relation(): void
    {
        $bhp = $this->makeBhpAsset();

        $this->assertCount(0, $bhp->photos);

        $bhp->photos()->create([
            'file_path'  => 'asset-photos/bhp-test.jpg',
            'sort_order' => 0,
        ]);

        $bhp->refresh();
        $this->assertCount(1, $bhp->photos);
        $this->assertDatabaseHas('asset_photos', [
            'asset_id'  => $bhp->id,
            'file_path' => 'asset-photos/bhp-test.jpg',
        ]);
    }

    // -----------------------------------------------------------------------
    // 2. Foto persisten setelah reload
    // -----------------------------------------------------------------------

    /** Foto BHP tetap tersimpan setelah reload model. */
    public function test_bhp_photo_persists_after_reload(): void
    {
        $bhp = $this->makeBhpAsset();
        $bhp->photos()->create(['file_path' => 'asset-photos/persist.jpg', 'sort_order' => 0]);

        // Reload dari database
        $reloaded = Asset::find($bhp->id);
        $this->assertCount(1, $reloaded->photos);
        $this->assertEquals('asset-photos/persist.jpg', $reloaded->photos->first()->file_path);
    }

    // -----------------------------------------------------------------------
    // 3. Hapus foto BHP
    // -----------------------------------------------------------------------

    /** Foto BHP dapat dihapus dan file ikut dihapus dari storage. */
    public function test_bhp_photo_can_be_deleted(): void
    {
        Storage::disk('s3')->put('asset-photos/delete-me.jpg', 'content');

        $bhp = $this->makeBhpAsset();
        $photo = $bhp->photos()->create([
            'file_path'  => 'asset-photos/delete-me.jpg',
            'sort_order' => 0,
        ]);

        $photo->delete();

        $this->assertDatabaseMissing('asset_photos', ['id' => $photo->id]);
        Storage::disk('s3')->assertMissing('asset-photos/delete-me.jpg');
    }

    // -----------------------------------------------------------------------
    // 4. URL foto BHP
    // -----------------------------------------------------------------------

    /** getUrlAttribute() mengembalikan URL yang tepat untuk foto BHP. */
    public function test_bhp_photo_url_attribute(): void
    {
        Storage::disk('s3')->put('asset-photos/url-test.jpg', 'content');

        $bhp   = $this->makeBhpAsset();
        $photo = $bhp->photos()->create([
            'file_path'  => 'asset-photos/url-test.jpg',
            'sort_order' => 0,
        ]);

        $this->assertNotNull($photo->url);
        $this->assertStringContainsString('url-test.jpg', $photo->url);
    }

    // -----------------------------------------------------------------------
    // 5. Bulk action AddPhoto di SupplyCategoryResource
    // -----------------------------------------------------------------------

    /** Bulk action Tambah Foto dapat menambahkan foto ke BHP Asset. */
    public function test_bulk_add_photo_works_for_bhp_assets(): void
    {
        $bhp1 = $this->makeBhpAsset(['name' => 'Kertas BHP 1']);
        $bhp2 = $this->makeBhpAsset(['name' => 'Kertas BHP 2']);

        // bhp1 sudah punya 1 foto
        $bhp1->photos()->create(['file_path' => 'asset-photos/existing.jpg', 'sort_order' => 0]);

        $newPhoto = 'asset-photos/new-bhp-photo.jpg';
        Storage::disk('s3')->put($newPhoto, 'content');

        Livewire::test(ListSupplyCategory::class)
            ->callTableBulkAction('addPhotoBulk', [$bhp1->id, $bhp2->id], data: [
                'new_photos'      => [$newPhoto],
                'allow_overwrite' => false,
            ])
            ->assertSuccessful();

        // bhp1: 1 foto existing + 1 baru = 2
        $this->assertEquals(2, $bhp1->photos()->count());
        // bhp2: 0 foto existing + 1 baru = 1
        $this->assertEquals(1, $bhp2->photos()->count());

        $this->assertDatabaseHas('asset_photos', [
            'asset_id'   => $bhp1->id,
            'file_path'  => $newPhoto,
            'sort_order' => 1,
        ]);
        $this->assertDatabaseHas('asset_photos', [
            'asset_id'   => $bhp2->id,
            'file_path'  => $newPhoto,
            'sort_order' => 0,
        ]);
    }

    /** Bulk action tidak memproses BHP ketika tidak ada record. */
    public function test_bulk_add_photo_returns_empty_when_no_records(): void
    {
        Livewire::test(ListSupplyCategory::class)
            ->callTableBulkAction('addPhotoBulk', [], data: [
                'new_photos'      => ['asset-photos/any.jpg'],
                'allow_overwrite' => false,
            ])
            ->assertSuccessful();

        $this->assertDatabaseCount('asset_photos', 0);
    }

    /** Overwrite mode menghapus foto lama BHP yang sudah penuh (3 foto). */
    public function test_bulk_add_photo_overwrites_bhp_photos_when_allowed(): void
    {
        $bhp = $this->makeBhpAsset();

        // Isi 3 foto (maksimum)
        for ($i = 0; $i < 3; $i++) {
            Storage::disk('s3')->put("asset-photos/old-{$i}.jpg", 'content');
            $bhp->photos()->create(['file_path' => "asset-photos/old-{$i}.jpg", 'sort_order' => $i]);
        }

        $newPhoto = 'asset-photos/replacement.jpg';
        Storage::disk('s3')->put($newPhoto, 'content');

        Livewire::test(ListSupplyCategory::class)
            ->callTableBulkAction('addPhotoBulk', [$bhp->id], data: [
                'new_photos'      => [$newPhoto],
                'allow_overwrite' => true,
            ])
            ->assertSuccessful();

        // Foto lama dihapus, foto baru masuk
        $this->assertEquals(1, $bhp->photos()->count());
        $this->assertDatabaseHas('asset_photos', ['asset_id' => $bhp->id, 'file_path' => $newPhoto]);
    }

    /** BHP yang sudah 3 foto di-skip jika allow_overwrite = false. */
    public function test_bulk_add_photo_skips_full_bhp_when_overwrite_not_allowed(): void
    {
        $bhp = $this->makeBhpAsset();

        for ($i = 0; $i < 3; $i++) {
            Storage::disk('s3')->put("asset-photos/full-{$i}.jpg", 'content');
            $bhp->photos()->create(['file_path' => "asset-photos/full-{$i}.jpg", 'sort_order' => $i]);
        }

        $newPhoto = 'asset-photos/should-not-be-added.jpg';
        Storage::disk('s3')->put($newPhoto, 'content');

        Livewire::test(ListSupplyCategory::class)
            ->callTableBulkAction('addPhotoBulk', [$bhp->id], data: [
                'new_photos'      => [$newPhoto],
                'allow_overwrite' => false,
            ])
            ->assertSuccessful();

        // Tetap 3 foto, tidak bertambah
        $this->assertEquals(3, $bhp->photos()->count());
        $this->assertDatabaseMissing('asset_photos', ['asset_id' => $bhp->id, 'file_path' => $newPhoto]);
    }
}
