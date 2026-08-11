<?php

namespace App\Filament\Resources\AssetResource\Pages;

use App\Filament\Resources\AssetResource;
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
        // Populate purchase data for the edit form if user has permission
        if (Auth::user()->hasPermissionTo('financial.view') && $this->record->purchase) {
            $data['purchase_data'] = $this->record->purchase->toArray();
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Extract purchase data
        if (isset($data['purchase_data'])) {
            $this->purchaseData = $data['purchase_data'];
            unset($data['purchase_data']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->purchaseData && Auth::user()->hasPermissionTo('financial.update')) {
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
