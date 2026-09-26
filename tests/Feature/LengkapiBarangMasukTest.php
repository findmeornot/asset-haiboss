<?php

namespace Tests\Feature;

use App\Filament\Inventory\Resources\AntrianBarangMasukResource;
use App\Filament\Inventory\Resources\AntrianBarangMasukResource\Pages\LengkapiBarangMasuk;
use App\Models\Asset;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Classification;
use App\Models\Location;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Role;
use App\Models\User;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class LengkapiBarangMasukTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Campus $campus;

    private Location $roomA;

    private Location $roomB;

    private Category $asetCategory;

    private Category $bhpCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

        $this->user = User::factory()->create();
        $this->user->roles()->attach(Role::where('name', 'Superadmin')->first()->id);
        $this->actingAs($this->user);

        $aset = Classification::create(['name' => 'Aset', 'slug' => 'aset']);
        Classification::create(['name' => 'Inventaris', 'slug' => 'inventaris']);
        $bhp = Classification::create(['name' => 'Barang Habis Pakai', 'slug' => 'barang-habis-pakai']);

        $this->asetCategory = Category::factory()->create();
        $this->asetCategory->classifications()->attach($aset->id);
        $this->bhpCategory = Category::factory()->create();
        $this->bhpCategory->classifications()->attach($bhp->id);

        $this->campus = Campus::factory()->create();
        $this->roomA = Location::factory()->create(['campus_id' => $this->campus->id]);
        $this->roomB = Location::factory()->create(['campus_id' => $this->campus->id]);
    }

    private function laporanOb(): Asset
    {
        return Asset::create([
            'keterangan' => 'Paket campur',
            'foto_resi' => 'barang-masuk/resi.jpg',
            'campus_id' => $this->campus->id,
            'location_id' => $this->roomA->id,
            'reported_by' => $this->user->id,
            'status' => 'baru_dilaporkan',
            'barcode' => '900001',
            'inventory_number' => 'INV9000001',
        ]);
    }

    public function test_satu_resi_banyak_jenis_barang_jadi_unit_per_qty_dengan_lokasi_masing_masing(): void
    {
        $laporan = $this->laporanOb();
        $undoRepeaterFake = Repeater::fake();

        Livewire::test(LengkapiBarangMasuk::class, ['record' => $laporan->getRouteKey()])
            ->fillForm([
                'items' => [
                    [
                        'row_id' => 'kursi',
                        'jenis_barang' => 'tidak_habis_pakai',
                        'name' => 'Kursi Kantor',
                        'unit_price' => 1500000,
                        'quantity' => 2,
                        'unit' => 'Unit',
                        'category_id' => $this->asetCategory->id,
                    ],
                    [
                        'row_id' => 'kertas',
                        'jenis_barang' => 'habis_pakai',
                        'name' => 'Kertas A4',
                        'unit_price' => 50000,
                        'quantity' => 3,
                        'unit' => 'Paket',
                        'category_id' => $this->bhpCategory->id,
                    ],
                ],
                'purchase_data' => ['ownership' => 'company'],
                'placements' => [
                    ['row_id' => 'kursi', 'campus_id' => $this->campus->id, 'location_id' => $this->roomA->id],
                    ['row_id' => 'kertas', 'campus_id' => $this->campus->id, 'location_id' => $this->roomB->id],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();

        $this->assertSame(1, Purchase::count());
        $this->assertEquals(3150000, (float) Purchase::first()->total_amount);
        $this->assertSame(2, PurchaseItem::count());

        $units = Asset::query()
            ->where(fn ($q) => $q->whereKey($laporan->id)->orWhere('intake_parent_id', $laporan->id))
            ->get();

        $this->assertCount(5, $units);
        $this->assertTrue($units->every(fn (Asset $unit) => $unit->status === 'menunggu_pengecekan'));
        $this->assertCount(5, $units->pluck('barcode')->unique());
        $this->assertCount(5, $units->pluck('inventory_number')->unique());

        $laporan->refresh();
        $this->assertSame('Kursi Kantor', $laporan->name);
        $this->assertSame('INV9000001', $laporan->inventory_number);
        $this->assertSame('barang-masuk/resi.jpg', $laporan->foto_resi);

        $kursi = $units->where('name', 'Kursi Kantor');
        $kertas = $units->where('name', 'Kertas A4');
        $this->assertCount(2, $kursi);
        $this->assertCount(3, $kertas);
        $this->assertTrue($kursi->every(fn (Asset $unit) => $unit->location_id === $this->roomA->id));
        $this->assertTrue($kertas->every(fn (Asset $unit) => $unit->location_id === $this->roomB->id));

        // Unit turunan bukan laporan OB.
        $this->assertSame(1, $units->whereNotNull('reported_by')->count());
        $this->assertSame(1, AntrianBarangMasukResource::getEloquentQuery()->count());

        // API: default 1 laporan, scope=unit semua unit.
        Sanctum::actingAs($this->user);
        $this->getJson('/api/v1/barang-masuk?status=menunggu_pengecekan')
            ->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/barang-masuk?status=menunggu_pengecekan&scope=unit')
            ->assertOk()->assertJsonPath('meta.total', 5);
    }

    public function test_lolos_step_daftar_barang_membangun_baris_penempatan_default_lokasi_laporan(): void
    {
        $laporan = $this->laporanOb();
        $undoRepeaterFake = Repeater::fake();

        $component = Livewire::test(LengkapiBarangMasuk::class, ['record' => $laporan->getRouteKey()])
            ->fillForm([
                'items' => [
                    [
                        'row_id' => 'kursi',
                        'jenis_barang' => 'tidak_habis_pakai',
                        'name' => 'Kursi Kantor',
                        'unit_price' => 1500000,
                        'quantity' => 2,
                        'category_id' => $this->asetCategory->id,
                    ],
                    [
                        'row_id' => 'meja',
                        'jenis_barang' => 'tidak_habis_pakai',
                        'name' => 'Meja',
                        'unit_price' => 2000000,
                        'quantity' => 1,
                        'category_id' => $this->asetCategory->id,
                    ],
                ],
            ])
            ->goToNextWizardStep()
            ->assertHasNoFormErrors();

        $undoRepeaterFake();

        $placements = array_values($component->get('data.placements'));

        $this->assertCount(2, $placements);
        $this->assertSame(['kursi', 'meja'], array_column($placements, 'row_id'));
        $this->assertSame($this->roomA->id, $placements[0]['location_id']);
        $this->assertSame($this->campus->id, $placements[1]['campus_id']);
    }

    public function test_api_update_tidak_bisa_melewati_invoice(): void
    {
        $laporan = $this->laporanOb();
        Sanctum::actingAs($this->user);

        $this->putJson("/api/v1/barang-masuk/{$laporan->ulid}", ['status' => 'menunggu_pengecekan'])
            ->assertStatus(422);

        $this->assertSame('baru_dilaporkan', $laporan->fresh()->status);
    }
}
