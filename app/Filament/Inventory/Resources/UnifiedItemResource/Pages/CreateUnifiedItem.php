<?php

namespace App\Filament\Inventory\Resources\UnifiedItemResource\Pages;

use App\Filament\Inventory\Resources\UnifiedItemResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Services\InventoryNumberGenerator;

class CreateUnifiedItem extends CreateRecord
{
    protected static string $resource = UnifiedItemResource::class;

    protected ?array $purchaseData = null;

    /**
     * Flag to detect when the creation is for Supply (InventoryBalance path).
     * This prevents afterCreate() from running logic that requires a persisted Asset record.
     */
    protected bool $isSupplyCreation = false;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => static::getResource()::getUrl('index')),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Extract purchase data
        if (isset($data['purchase_data'])) {
            $this->purchaseData = $data['purchase_data'];

            if (array_key_exists('ownership', $this->purchaseData)) {
                $data['ownership'] = $this->purchaseData['ownership'];
                // We keep it in purchaseData for the Purchase model
            }
            if (array_key_exists('unit', $this->purchaseData)) {
                $data['unit'] = $this->purchaseData['unit'];
                // We keep it in purchaseData for the PurchaseItem model
            }
            // Quantity is deliberately kept in purchaseData to be used in handleRecordCreation

            unset($data['purchase_data']);
        }

        // Handle Tahun Perolehan & Harga Perolehan kosong
        $tahunUnknown = empty($this->purchaseData['purchase_date']);
        $hargaUnknown = empty($this->purchaseData['unit_price']);

        $unknownInfo = array_filter([
            $tahunUnknown ? 'tahun perolehan tidak diketahui' : null,
            $hargaUnknown ? 'harga perolehan tidak diketahui' : null,
        ]);

        if (!empty($unknownInfo)) {
            $suffix = '(' . implode(', ', $unknownInfo) . ')';
            $existingNotes = trim($data['notes'] ?? '');

            // Only append if it's not already in the notes
            if ($existingNotes === '') {
                $data['notes'] = $suffix;
            } elseif (!str_contains($existingNotes, $suffix)) {
                $data['notes'] = $existingNotes . ' ' . $suffix;
            }
        }

        // Validate threshold before creating
        $unitPrice = isset($this->purchaseData['unit_price']) && $this->purchaseData['unit_price'] !== '' ? (float) $this->purchaseData['unit_price'] : null;
        if ($unitPrice !== null) {
            $classification = \App\Models\Classification::find($data['classification_id'] ?? null);
            if ($classification && strtolower($classification->slug) === 'aset' && $unitPrice < 1000000) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'data.purchase_data.unit_price' => 'Aset harus memiliki harga perolehan >= Rp1.000.000.',
                ]);
            }
            if ($classification && strtolower($classification->slug) === 'inventaris' && $unitPrice >= 1000000) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'data.purchase_data.unit_price' => 'Inventaris harus memiliki harga perolehan < Rp1.000.000.',
                ]);
            }
        }

        return $data;
    }

    protected ?string $createdClassificationSlug = null;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            $quantity = isset($this->purchaseData['quantity']) ? (int) $this->purchaseData['quantity'] : 1;
            if ($quantity < 1) $quantity = 1;

            $unitPrice  = isset($this->purchaseData['unit_price']) && $this->purchaseData['unit_price'] !== '' ? (float) $this->purchaseData['unit_price']  : null;
            $totalPrice = $unitPrice !== null ? $unitPrice * $quantity : null;
            $purchaseDate = $this->purchaseData['purchase_date'] ?? null;
            $ownership    = $this->purchaseData['ownership']     ?? 'company';
            $unit         = $this->purchaseData['unit']          ?? null;

            // Pre-fetch classification and category objects for inventory number generation
            $classification = \App\Models\Classification::find($data['classification_id'] ?? null);
            $category       = \App\Models\Category::find($data['category_id']       ?? null);

            // 1. Create Purchase header
            $purchase = \App\Models\Purchase::create([
                'purchase_date' => $purchaseDate,
                'ownership'     => $ownership,
                'total_amount'  => $totalPrice,
            ]);

            $this->createdClassificationSlug = $classification ? strtolower($classification->slug) : null;

            $purchaseItemData = [
                'purchase_id'       => $purchase->id,
                'category_id'       => $data['category_id']       ?? null,
                'classification_id' => $data['classification_id'] ?? null,
                'name'              => $data['name']               ?? 'Asset Baru',
                'quantity'          => $quantity,
                'unit'              => $unit,
                'unit_price'        => $unitPrice,
                'total_price'       => $totalPrice,
                // Business Rule: Capitalization is based on UNIT PRICE, not total price
                'is_capitalized'    => \App\Models\PurchaseItem::isCapitalizable($unitPrice, $classification),
            ];

            if ($classification && strtolower($classification->slug) === 'persediaan-barang') {
                // 2a. Supply path: update InventoryBalance — NO individual Asset records created.
                $this->isSupplyCreation = true;

                $balance = \App\Models\InventoryBalance::where(
                    [
                        'category_id' => $data['category_id'],
                        'name'        => $data['name'] ?? 'Asset Baru',
                        'location_id' => $data['location_id'] ?? null,
                    ]
                )->first();
                
                $isNewBalance = false;
                
                if (!$balance) {
                    $balance = \App\Models\InventoryBalance::create(
                        [
                            'category_id' => $data['category_id'],
                            'name'        => $data['name'] ?? 'Asset Baru',
                            'location_id' => $data['location_id'] ?? null,
                            'campus_id' => $data['campus_id'] ?? null,
                            'quantity'  => 0,
                            'master_barcode' => \App\Services\SupplyBarcodeGenerator::generateMaster(),
                            'latest_sequence' => 0,
                            'has_pure_master_unit' => false,
                            'pic_id' => $data['pic_id'] ?? null,
                            'status' => $data['status'] ?? 'stock',
                            'notes' => $data['notes'] ?? null,
                        ]
                    );
                    $isNewBalance = true;
                }

                // Add purchased quantity to the running balance
                $balance->increment('quantity', $quantity);

                // Link PurchaseItem to the balance for purchase history traceability
                $purchaseItemData['inventory_balance_id'] = $balance->id;
                $purchaseItemData['is_capitalized'] = false; // Always false for supply
                $purchaseItem = \App\Models\PurchaseItem::create($purchaseItemData);

                // BARCODE LOGIC
                if ($isNewBalance && $quantity === 1) {
                    $balance->update(['has_pure_master_unit' => true]);
                    $balance->units()->create([
                        'purchase_item_id' => $purchaseItem->id,
                        'sub_barcode' => $balance->master_barcode,
                        'status' => 'available'
                    ]);
                } else {
                    $subBarcodes = \App\Services\SupplyBarcodeGenerator::generateSub($balance, $quantity);
                    $unitRecords = [];
                    foreach ($subBarcodes as $sb) {
                        $unitRecords[] = [
                            'inventory_balance_id' => $balance->id,
                            'purchase_item_id' => $purchaseItem->id,
                            'sub_barcode' => $sb,
                            'status' => 'available',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    \App\Models\InventoryBalanceUnit::insert($unitRecords);
                }

                return new \App\Models\UnifiedItem();
            }

            // 2b. Asset / Inventory path: create PurchaseItem, then N individual Asset records
            $purchaseItem = \App\Models\PurchaseItem::create($purchaseItemData);

            $firstAsset = null;

            // 3. Create N individual Asset records (1 record = 1 physical unit)
            for ($i = 0; $i < $quantity; $i++) {
                // Each unit gets its own unique inventory_number (SKU)
                $inventoryNumber = \App\Services\InventoryNumberGenerator::generate($classification, $category);

                $assetData                     = $data;
                $assetData['inventory_number'] = $inventoryNumber;
                $assetData['purchase_item_id'] = $purchaseItem->id;
                // Barcode is generated automatically by AssetObserver::creating()

                $asset = \App\Models\Asset::create($assetData);

                if ($i === 0) {
                    $firstAsset = $asset;
                }
            }

            return new \App\Models\UnifiedItem();
        });
    }

    protected function afterCreate(): void
    {
        // Supply creation does not produce a persisted Asset record.
        // Skip any post-create logic that requires an Asset with a valid database ID.
        if ($this->isSupplyCreation) {
            return;
        }
        // Legacy AssetPurchase creation is removed (Milestone 9).
    }

    protected function getRedirectUrl(): string
    {
        if ($this->createdClassificationSlug === 'aset') {
            return \App\Filament\Inventory\Resources\AssetCategoryResource::getUrl('index');
        } elseif ($this->createdClassificationSlug === 'inventaris') {
            return \App\Filament\Inventory\Resources\InventoryCategoryResource::getUrl('index');
        } elseif ($this->createdClassificationSlug === 'persediaan-barang') {
            return \App\Filament\Inventory\Resources\InventoryBalanceResource::getUrl('index');
        }

        return $this->getResource()::getUrl('index');
    }
}
