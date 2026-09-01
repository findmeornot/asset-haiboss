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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Generate inventory number automatically
        $data['inventory_number'] = InventoryNumberGenerator::generate();

        // Extract purchase data
        if (isset($data['purchase_data'])) {
            $this->purchaseData = $data['purchase_data'];
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

    protected function afterCreate(): void
    {
        if ($this->purchaseData) {
            $this->record->purchase()->create($this->purchaseData);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
