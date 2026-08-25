<?php

namespace App\Filament\Inventory\Resources\AssetResource\Pages;

use App\Filament\Inventory\Resources\AssetResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Services\InventoryNumberGenerator;

class CreateAsset extends CreateRecord
{
    protected static string $resource = AssetResource::class;

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

        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            $quantity = isset($this->purchaseData['quantity']) ? (int) $this->purchaseData['quantity'] : 1;
            if ($quantity < 1) $quantity = 1;

            $unitPrice  = isset($this->purchaseData['unit_price'])  ? (float) $this->purchaseData['unit_price']  : 0;
            $totalPrice = isset($this->purchaseData['total_price'])  ? (float) $this->purchaseData['total_price'] : ($unitPrice * $quantity);
            $purchaseDate = $this->purchaseData['purchase_date'] ?? null;
            $ownership    = $this->purchaseData['ownership']     ?? 'company';
            $unit         = $this->purchaseData['unit']          ?? null;

            // 1. Create Purchase header
            $purchase = \App\Models\Purchase::create([
                'purchase_date' => $purchaseDate,
                'ownership'     => $ownership,
                'total_amount'  => $totalPrice,
            ]);

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
                'is_capitalized'    => \App\Models\PurchaseItem::isCapitalizable($unitPrice),
            ];

            // Pre-fetch classification and category objects for inventory number generation
            $classification = \App\Models\Classification::find($data['classification_id'] ?? null);
            $category       = \App\Models\Category::find($data['category_id']       ?? null);

            if ($category && $category->type === 'supply') {
                // 2a. Supply path: update InventoryBalance — NO individual Asset records created.
                $this->isSupplyCreation = true;

                $balance = \App\Models\InventoryBalance::firstOrCreate(
                    [
                        'category_id' => $category->id,
                        'name'        => $data['name'] ?? 'Asset Baru',
                        'location_id' => $data['location_id'] ?? null,
                    ],
                    [
                        'campus_id' => $data['campus_id'] ?? null,
                        'quantity'  => 0,
                    ]
                );

                // Add purchased quantity to the running balance
                $balance->increment('quantity', $quantity);

                // Link PurchaseItem to the balance for purchase history traceability
                $purchaseItemData['inventory_balance_id'] = $balance->id;
                \App\Models\PurchaseItem::create($purchaseItemData);

                // Return an unsaved Asset instance to satisfy Filament's Model return type contract.
                // afterCreate() detects isSupplyCreation=true and skips any asset-level logic.
                // getRedirectUrl() already returns the index page, so no record key is required.
                return new \App\Models\Asset();
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

                $asset = static::getModel()::create($assetData);

                if ($i === 0) {
                    $firstAsset = $asset;
                }
            }

            return $firstAsset;
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
        return $this->getResource()::getUrl('index');
    }
}
