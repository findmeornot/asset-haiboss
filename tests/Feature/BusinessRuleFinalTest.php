<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Classification;
use App\Models\Category;
use App\Models\Campus;
use App\Models\Location;
use App\Models\User;
use App\Models\Asset;
use App\Models\InventoryBalance;
use App\Models\PurchaseItem;
use App\Services\DepreciationService;
use App\Filament\Inventory\Resources\UnifiedItemResource\Pages\CreateUnifiedItem;
use App\Filament\Inventory\Resources\AssetResource\Pages\EditAsset;
use App\Http\Controllers\ReportController;
use Illuminate\Http\Request;
use Livewire\Livewire;

class BusinessRuleFinalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

        $this->user = User::factory()->create();
        $superadminRole = \App\Models\Role::where('name', 'Superadmin')->first();
        if ($superadminRole) {
            $this->user->roles()->attach($superadminRole->id);
        }
        
        $this->actingAs($this->user);

        $this->asetClass = Classification::create(['name' => 'Aset', 'slug' => 'aset']);
        $this->inventarisClass = Classification::create(['name' => 'Inventaris', 'slug' => 'inventaris']);
        $this->persediaanClass = Classification::create(['name' => 'Persediaan Barang', 'slug' => 'persediaan-barang']);

        $this->category = Category::create(['name' => 'Elektronik', 'code' => 'ELK']);
        $this->category->classifications()->attach([$this->asetClass->id, $this->inventarisClass->id, $this->persediaanClass->id]);

        $this->campus = Campus::create(['name' => 'Gedung Utama']);
        $this->location = Location::create(['name' => 'Ruang 101', 'campus_id' => $this->campus->id]);
    }

    public function test_create_aset_null_price_passes()
    {
        Livewire::test(CreateUnifiedItem::class)
            ->fillForm([
                'classification_id' => $this->asetClass->id,
                'category_id' => $this->category->id,
                'name' => 'Laptop A',
                'campus_id' => $this->campus->id,
                'location_id' => $this->location->id,
                'status' => 'stock',
                'purchase_data' => [
                    'unit_price' => null,
                    'quantity' => 1,
                    'ownership' => 'company'
                ]
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $asset = Asset::first();
        $this->assertNotNull($asset);
        $this->assertNull($asset->purchaseItem->unit_price);
        $this->assertFalse((bool)$asset->purchaseItem->is_capitalized);
        $this->assertNotNull($asset->inventory_number);
        
        $depreciation = DepreciationService::calculate($asset);
        $this->assertFalse($depreciation['is_depreciable']);
    }

    public function test_create_inventaris_null_price_passes()
    {
        Livewire::test(CreateUnifiedItem::class)
            ->fillForm([
                'classification_id' => $this->inventarisClass->id,
                'category_id' => $this->category->id,
                'name' => 'Mouse B',
                'campus_id' => $this->campus->id,
                'location_id' => $this->location->id,
                'status' => 'stock',
                'purchase_data' => [
                    'unit_price' => null,
                    'quantity' => 1,
                    'ownership' => 'company'
                ]
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $asset = Asset::first();
        $this->assertNotNull($asset);
        $this->assertNull($asset->purchaseItem->unit_price);
    }

    public function test_create_persediaan_null_price_passes()
    {
        Livewire::test(CreateUnifiedItem::class)
            ->fillForm([
                'classification_id' => $this->persediaanClass->id,
                'category_id' => $this->category->id,
                'name' => 'Kertas A4',
                'campus_id' => $this->campus->id,
                'location_id' => $this->location->id,
                'status' => 'stock',
                'purchase_data' => [
                    'unit_price' => null,
                    'quantity' => 10,
                    'ownership' => 'company'
                ]
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $balance = InventoryBalance::first();
        $this->assertNotNull($balance);
        $this->assertEquals(10, $balance->quantity);
        $this->assertNull($balance->purchaseItems->first()->unit_price);
    }

    public function test_create_aset_999999_fails()
    {
        Livewire::test(CreateUnifiedItem::class)
            ->fillForm([
                'classification_id' => $this->asetClass->id,
                'category_id' => $this->category->id,
                'name' => 'Laptop C',
                'campus_id' => $this->campus->id,
                'location_id' => $this->location->id,
                'purchase_data' => [
                    'unit_price' => 999999,
                    'quantity' => 1,
                    'ownership' => 'company'
                ]
            ])
            ->call('create')
            ->assertHasFormErrors(['data.purchase_data.unit_price']);
    }

    public function test_create_aset_1000000_passes()
    {
        Livewire::test(CreateUnifiedItem::class)
            ->fillForm([
                'classification_id' => $this->asetClass->id,
                'category_id' => $this->category->id,
                'name' => 'Laptop D',
                'campus_id' => $this->campus->id,
                'location_id' => $this->location->id,
                'status' => 'stock',
                'purchase_data' => [
                    'unit_price' => 1000000,
                    'quantity' => 1,
                    'ownership' => 'company'
                ]
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_create_inventaris_999999_passes()
    {
        Livewire::test(CreateUnifiedItem::class)
            ->fillForm([
                'classification_id' => $this->inventarisClass->id,
                'category_id' => $this->category->id,
                'name' => 'Mouse E',
                'campus_id' => $this->campus->id,
                'location_id' => $this->location->id,
                'status' => 'stock',
                'purchase_data' => [
                    'unit_price' => 999999,
                    'quantity' => 1,
                    'ownership' => 'company'
                ]
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_create_inventaris_1000000_fails()
    {
        Livewire::test(CreateUnifiedItem::class)
            ->fillForm([
                'classification_id' => $this->inventarisClass->id,
                'category_id' => $this->category->id,
                'name' => 'Mouse F',
                'campus_id' => $this->campus->id,
                'location_id' => $this->location->id,
                'purchase_data' => [
                    'unit_price' => 1000000,
                    'quantity' => 1,
                    'ownership' => 'company'
                ]
            ])
            ->call('create')
            ->assertHasFormErrors(['data.purchase_data.unit_price']);
    }

    public function test_update_aset_null_to_1000000_passes_and_immutable()
    {
        $purchase = \App\Models\Purchase::create(['total_amount' => null]);
        $item = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'classification_id' => $this->asetClass->id,
            'unit_price' => null,
            'total_price' => null,
            'is_capitalized' => false,
            'name' => 'Aset G',
            'quantity' => 1,
        ]);
        $asset = Asset::create([
            'name' => 'Aset G',
            'classification_id' => $this->asetClass->id,
            'category_id' => $this->category->id,
            'purchase_item_id' => $item->id,
            'campus_id' => $this->campus->id,
            'location_id' => $this->location->id,
            'status' => 'stock',
            'inventory_number' => 'INV-G',
            'ownership' => 'company'
        ]);

        Livewire::test(EditAsset::class, ['record' => $asset->id])
            ->fillForm([
                'purchase_data' => [
                    'unit_price' => 1000000
                ]
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals(1000000, $item->fresh()->unit_price);
        
        // Attempt to update it again to 1500000
        Livewire::test(EditAsset::class, ['record' => $asset->id])
            ->fillForm([
                'purchase_data' => [
                    'unit_price' => 1500000
                ]
            ])
            ->call('save')
            ->assertHasNoFormErrors(); // It ignores the update without error

        $this->assertEquals(1000000, $item->fresh()->unit_price);
    }

    public function test_legacy_depreciation()
    {
        // Case A
        $assetPurchase = new \App\Models\AssetPurchase([
            'total_price' => 3000000,
            'quantity' => 10,
            'purchase_date' => '2023-01-01',
        ]);
        
        $asset = Asset::create([
            'name' => 'Legacy A',
            'classification_id' => $this->asetClass->id,
            'campus_id' => $this->campus->id,
            'location_id' => $this->location->id,
            'status' => 'stock',
            'inventory_number' => 'INV-001',
            'category_id' => $this->category->id,
            'ownership' => 'company',
        ]);
        $asset->purchase()->save($assetPurchase);

        $dep = DepreciationService::calculate($asset);
        // Unit cost = 3000000 / 10 = 300000. So it's < 1000000, NOT depreciable
        $this->assertFalse($dep['is_depreciable']);

        // Case B: 2000000 / 2 = 1000000. Is depreciable.
        $assetPurchase2 = new \App\Models\AssetPurchase([
            'total_price' => 2000000,
            'quantity' => 2,
            'purchase_date' => '2023-01-01',
        ]);
        
        $asset2 = Asset::create([
            'name' => 'Legacy B',
            'classification_id' => $this->asetClass->id,
            'campus_id' => $this->campus->id,
            'location_id' => $this->location->id,
            'status' => 'stock',
            'inventory_number' => 'INV-002',
            'category_id' => $this->category->id,
            'ownership' => 'company',
        ]);
        $asset2->purchase()->save($assetPurchase2);

        $dep2 = DepreciationService::calculate($asset2);
        $this->assertTrue($dep2['is_depreciable']);
        $this->assertEquals('1000000.00', $dep2['acquisition_cost']);
    }

    public function test_reporting_export()
    {
        $purchase = \App\Models\Purchase::create(['total_amount' => 1500000]);
        // Setup data
        $item1 = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'classification_id' => $this->asetClass->id,
            'name' => 'Aset X', 'quantity' => 1, 'unit_price' => 1000000, 'total_price' => 1000000, 'is_capitalized' => true
        ]);
        $asset = Asset::create(['name' => 'Aset X', 'classification_id' => $this->asetClass->id, 'category_id' => $this->category->id, 'purchase_item_id' => $item1->id, 'campus_id' => $this->campus->id, 'location_id' => $this->location->id, 'inventory_number' => 'X01', 'ownership' => 'company', 'status' => 'stock']);

        $item2 = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'classification_id' => $this->inventarisClass->id,
            'name' => 'Inv X', 'quantity' => 1, 'unit_price' => 500000, 'total_price' => 500000, 'is_capitalized' => false
        ]);
        $inv = Asset::create(['name' => 'Inv X', 'classification_id' => $this->inventarisClass->id, 'category_id' => $this->category->id, 'purchase_item_id' => $item2->id, 'campus_id' => $this->campus->id, 'location_id' => $this->location->id, 'inventory_number' => 'X02', 'ownership' => 'company', 'status' => 'stock']);

        $bal = InventoryBalance::create(['name' => 'Persediaan X', 'category_id' => $this->category->id, 'campus_id' => $this->campus->id, 'location_id' => $this->location->id, 'quantity' => 50]);

        $controller = new ReportController();
        
        $reqAset = Request::create('/export', 'GET', ['report_type' => 'aset_only']);
        $respAset = $controller->exportExcel($reqAset);
        ob_start();
        $respAset->sendContent();
        $contentAset = ob_get_clean();
        $this->assertStringContainsString('Aset X', $contentAset);
        $this->assertStringNotContainsString('Inv X', $contentAset);

        $reqInv = Request::create('/export', 'GET', ['report_type' => 'inventaris_only']);
        $respInv = $controller->exportExcel($reqInv);
        ob_start();
        $respInv->sendContent();
        $contentInv = ob_get_clean();
        $this->assertStringContainsString('Inv X', $contentInv);
        $this->assertStringNotContainsString('Aset X', $contentInv);

        $reqPersediaan = Request::create('/export', 'GET', ['report_type' => 'persediaan_only']);
        $respPersediaan = $controller->exportExcel($reqPersediaan);
        ob_start();
        $respPersediaan->sendContent();
        $contentPersediaan = ob_get_clean();
        $this->assertStringContainsString('Persediaan X', $contentPersediaan);
    }
}
