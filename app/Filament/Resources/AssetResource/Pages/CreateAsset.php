<?php

namespace App\Filament\Resources\AssetResource\Pages;

use App\Filament\Resources\AssetResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Services\InventoryNumberGenerator;

class CreateAsset extends CreateRecord
{
    protected static string $resource = AssetResource::class;

    protected ?array $purchaseData = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Generate inventory number automatically
        $data['inventory_number'] = InventoryNumberGenerator::generate();

        // Extract purchase data
        if (isset($data['purchase_data'])) {
            $this->purchaseData = $data['purchase_data'];
            unset($data['purchase_data']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->purchaseData) {
            $this->record->purchase()->create($this->purchaseData);
        }
    }
}
