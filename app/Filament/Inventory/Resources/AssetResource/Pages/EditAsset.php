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
        if ($this->record->purchase) {
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
                unset($this->purchaseData['ownership']);
            }
            if (array_key_exists('unit', $this->purchaseData)) {
                $data['unit'] = $this->purchaseData['unit'];
                unset($this->purchaseData['unit']);
            }
            if (array_key_exists('quantity', $this->purchaseData)) {
                unset($this->purchaseData['quantity']);
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
            $oldPrice = $this->record->purchase ? $this->record->purchase->unit_price : null;
            $newPrice = $this->purchaseData['unit_price'] ?? null;

            // Update or Create Purchase
            $this->record->purchase()->updateOrCreate(
                ['asset_id' => $this->record->id],
                $this->purchaseData
            );

            // Log Price History if changed
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
