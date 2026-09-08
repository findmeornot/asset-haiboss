<?php

namespace App\Filament\Inventory\Resources\MutationResource\Pages;

use App\Filament\Inventory\Resources\MutationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;
use App\Services\MutationValidationService;

class EditMutation extends EditRecord
{
    protected static string $resource = MutationResource::class;

    public ?array $assetIds = null;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['asset_ids'])) {
            $this->assetIds = $data['asset_ids'];
            unset($data['asset_ids']);
        }
        
        $sourceLocationId = $data['source_location_id'] ?? $this->record->source_location_id;
        $sourceCampusId = $data['source_campus_id'] ?? $this->record->source_campus_id;
        $destLocationId = $data['destination_location_id'] ?? $this->record->destination_location_id;
        $destCampusId = $data['destination_campus_id'] ?? $this->record->destination_campus_id;
        
        // Location validation
        if ($sourceCampusId === $destCampusId && $sourceLocationId === $destLocationId) {
            throw ValidationException::withMessages([
                'destination_location_id' => 'Lokasi tujuan tidak boleh sama dengan lokasi asal.',
            ]);
        }
        
        // Campus/Location Hierarchy Validation
        $sourceLocation = \App\Models\Location::find($sourceLocationId);
        if ($sourceLocation && $sourceLocation->campus_id != $sourceCampusId) {
            throw ValidationException::withMessages(['source_location_id' => 'Lokasi asal tidak sesuai dengan gedung asal.']);
        }
        $destLocation = \App\Models\Location::find($destLocationId);
        if ($destLocation && $destLocation->campus_id != $destCampusId) {
            throw ValidationException::withMessages(['destination_location_id' => 'Lokasi tujuan tidak sesuai dengan gedung tujuan.']);
        }
        
        return $data;
    }

    protected function afterSave(): void
    {
        $data = $this->form->getRawState();
        $type = $this->record->type;
        $assetIdsToSave = $this->assetIds ?? $data['asset_ids'] ?? [];
        
        $sourceLocationId = $this->record->source_location_id;
        $sourceCampusId = $this->record->source_campus_id;

        if (in_array($type, ['asset', 'inventory'])) {
            MutationValidationService::validateAndLockItems($type, $assetIdsToSave, $sourceCampusId, $sourceLocationId);
            
            // Delete only after validation is successful
            $this->record->items()->whereNotNull('asset_id')->delete();
            
            foreach ($assetIdsToSave as $assetId) {
                $this->record->items()->create([
                    'asset_id' => $assetId,
                    'quantity' => 1
                ]);
            }
        } else if ($type === 'consumable') {
            $items = $data['items'] ?? [];
            MutationValidationService::validateAndLockItems($type, $items, $sourceCampusId, $sourceLocationId);
            
            // Delete only after validation is successful
            $this->record->items()->whereNotNull('inventory_balance_id')->delete();
            
            foreach ($items as $item) {
                if (!isset($item['inventory_balance_id']) || !isset($item['quantity'])) continue;
                $this->record->items()->create([
                    'inventory_balance_id' => $item['inventory_balance_id'],
                    'quantity' => $item['quantity']
                ]);
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
