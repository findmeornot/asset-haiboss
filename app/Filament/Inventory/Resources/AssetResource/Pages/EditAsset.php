<?php

namespace App\Filament\Inventory\Resources\AssetResource\Pages;

use App\Filament\Inventory\Resources\AssetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\AssetPriceHistory;
use Illuminate\Support\Facades\Auth;

class EditAsset extends EditRecord
{
    protected static string $resource = AssetResource::class;

    protected ?array $purchaseData = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => static::getResource()::getUrl('index')),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['purchase_data'] = [];
        
        if (array_key_exists('ownership', $data)) {
            $data['purchase_data']['ownership'] = $data['ownership'];
        }
        if (array_key_exists('unit', $data)) {
            $data['purchase_data']['unit'] = $data['unit'];
        }

        // Populate purchase data for the edit form if user has permission
        if ($this->record->purchaseItem) {
            // New Architecture
            $purchaseItemData = $this->record->purchaseItem->toArray();
            
            // Bring ownership and purchase_date from parent Purchase if available
            if ($this->record->purchaseItem->purchase) {
                $purchaseItemData['ownership'] = $this->record->purchaseItem->purchase->ownership;
                $purchaseItemData['purchase_date'] = $this->record->purchaseItem->purchase->purchase_date;
            }
            
            $data['purchase_data'] = array_merge($data['purchase_data'], $purchaseItemData);
        } elseif ($this->record->purchase) {
            // Legacy Fallback
            $data['purchase_data'] = array_merge($data['purchase_data'], $this->record->purchase->toArray());
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Extract purchase data
        if (isset($data['purchase_data'])) {
            $this->purchaseData = $data['purchase_data'];

            if (array_key_exists('ownership', $this->purchaseData)) {
                $data['ownership'] = $this->purchaseData['ownership'];
                // Keep it in purchaseData for saving
            }
            if (array_key_exists('unit', $this->purchaseData)) {
                $data['unit'] = $this->purchaseData['unit'];
                // Keep it in purchaseData for saving
            }
            if (array_key_exists('quantity', $this->purchaseData)) {
                // Quantity is ignored during Edit to prevent orphan assets or missing assets.
                // It must be edited at the PurchaseItem level directly if supported in the future.
                unset($this->purchaseData['quantity']);
            }

            // Enforce Immutability & Boundary Rules
            $oldPrice = null;
            if ($this->record->purchaseItem) {
                $oldPrice = $this->record->purchaseItem->unit_price;
            } elseif ($this->record->purchase) {
                $oldPrice = $this->record->purchase->total_price !== null ? $this->record->purchase->total_price / max(1, $this->record->purchase->quantity ?? 1) : null;
            }

            $newPrice = isset($this->purchaseData['unit_price']) && $this->purchaseData['unit_price'] !== '' ? (float) $this->purchaseData['unit_price'] : null;

            if ($oldPrice !== null) {
                // Immutability rule: if already has price, it cannot be changed
                if ($newPrice !== null && $newPrice != $oldPrice) {
                    unset($this->purchaseData['unit_price']);
                    unset($this->purchaseData['total_price']);
                }
            } else {
                // If it was null, and now they are setting a price, validate boundary
                if ($newPrice !== null) {
                    $classification = $this->record->classification;
                    if ($classification && strtolower($classification->slug) === 'aset' && $newPrice < 1000000) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'purchase_data.unit_price' => 'Aset harus memiliki harga perolehan >= Rp1.000.000.',
                        ]);
                    }
                    if ($classification && strtolower($classification->slug) === 'inventaris' && $newPrice >= 1000000) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'purchase_data.unit_price' => 'Inventaris harus memiliki harga perolehan < Rp1.000.000.',
                        ]);
                    }
                    // Price is valid, allow it to be set
                    $this->purchaseData['unit_price'] = $newPrice;
                }
            }

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

    protected function afterSave(): void
    {
        if ($this->purchaseData) {
            if ($this->record->purchaseItem) {
                // New Architecture
                $oldPrice = $this->record->purchaseItem->unit_price;
                $newPrice = $this->purchaseData['unit_price'] ?? null;
                
                // Only update unit_price and total_price on PurchaseItem
                // This will affect ALL assets under this PurchaseItem
                $purchaseItemUpdates = [];
                if (array_key_exists('unit_price', $this->purchaseData)) {
                    $purchaseItemUpdates['unit_price'] = $this->purchaseData['unit_price'];
                    // Recalculate total_price based on the PurchaseItem's original quantity
                    $purchaseItemUpdates['total_price'] = $this->purchaseData['unit_price'] !== null ? $this->purchaseData['unit_price'] * $this->record->purchaseItem->quantity : null;
                    // Re-evaluate capitalization rule
                    $purchaseItemUpdates['is_capitalized'] = \App\Models\PurchaseItem::isCapitalizable($this->purchaseData['unit_price'], $this->record->classification);
                }
                if (isset($this->purchaseData['unit'])) {
                    $purchaseItemUpdates['unit'] = $this->purchaseData['unit'];
                }
                
                if (!empty($purchaseItemUpdates)) {
                    $this->record->purchaseItem->update($purchaseItemUpdates);
                }

                // Update Parent Purchase if ownership or purchase_date changed
                if ($this->record->purchaseItem->purchase) {
                    $purchaseUpdates = [];
                    if (isset($this->purchaseData['ownership'])) {
                        $purchaseUpdates['ownership'] = $this->purchaseData['ownership'];
                    }
                    if (array_key_exists('purchase_date', $this->purchaseData)) {
                        $purchaseUpdates['purchase_date'] = $this->purchaseData['purchase_date'];
                    }
                    if (isset($purchaseItemUpdates['total_price'])) {
                        // Ideally we should recalculate the total_amount of the Purchase
                        // by summing all its PurchaseItems. For now, we just update it if it's a 1-item purchase.
                        $totalAmount = $this->record->purchaseItem->purchase->purchaseItems()->sum('total_price');
                        $purchaseUpdates['total_amount'] = $totalAmount;
                    }
                    
                    if (!empty($purchaseUpdates)) {
                        $this->record->purchaseItem->purchase->update($purchaseUpdates);
                    }
                }

                if ($oldPrice != $newPrice) {
                    AssetPriceHistory::create([
                        'asset_id' => $this->record->id,
                        'old_price' => $oldPrice,
                        'new_price' => $newPrice,
                        'changed_by' => Auth::id(),
                    ]);
                }
                
            } else {
                // Legacy Fallback
                $oldPrice = $this->record->purchase ? $this->record->purchase->unit_price : null;
                $newPrice = $this->purchaseData['unit_price'] ?? null;

                $this->record->purchase()->updateOrCreate(
                    ['asset_id' => $this->record->id],
                    $this->purchaseData
                );

                if ($oldPrice != $newPrice) {
                    AssetPriceHistory::create([
                        'asset_id' => $this->record->id,
                        'old_price' => $oldPrice,
                        'new_price' => $newPrice,
                        'changed_by' => Auth::id(),
                    ]);
                }
            }
        }
    }
}
