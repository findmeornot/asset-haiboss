<?php

namespace App\Filament\Inventory\Resources\MutationResource\Pages;

use App\Filament\Inventory\Resources\MutationResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Services\MutationValidationService;

class CreateMutation extends CreateRecord
{
    protected static string $resource = MutationResource::class;

    public ?array $assetIds = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['requested_by'] = Auth::id();
        $data['status'] = 'pending';
        
        if (isset($data['asset_ids'])) {
            $this->assetIds = $data['asset_ids'];
            unset($data['asset_ids']);
        }
        
        // Location validation
        if ($data['source_campus_id'] === $data['destination_campus_id'] && 
            $data['source_location_id'] === $data['destination_location_id']) {
            throw ValidationException::withMessages([
                'destination_location_id' => 'Lokasi tujuan tidak boleh sama dengan lokasi asal.',
            ]);
        }
        
        // Campus/Location Hierarchy Validation
        $sourceLocation = \App\Models\Location::find($data['source_location_id']);
        if ($sourceLocation && $sourceLocation->campus_id != $data['source_campus_id']) {
            throw ValidationException::withMessages(['source_location_id' => 'Lokasi asal tidak sesuai dengan gedung asal.']);
        }
        $destLocation = \App\Models\Location::find($data['destination_location_id']);
        if ($destLocation && $destLocation->campus_id != $data['destination_campus_id']) {
            throw ValidationException::withMessages(['destination_location_id' => 'Lokasi tujuan tidak sesuai dengan gedung tujuan.']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $data = $this->form->getRawState();
        $type = $this->record->type;
        $assetIdsToSave = $this->assetIds ?? $data['asset_ids'] ?? [];
        
        $sourceLocationId = $this->record->source_location_id;
        $sourceCampusId = $this->record->source_campus_id;

        if (in_array($type, ['asset', 'inventory'])) {
            MutationValidationService::validateAndLockItems($type, $assetIdsToSave, $sourceCampusId, $sourceLocationId);
            
            foreach ($assetIdsToSave as $assetId) {
                $this->record->items()->create([
                    'asset_id' => $assetId,
                    'quantity' => 1
                ]);
            }
        } else if ($type === 'consumable') {
            $items = $data['items'] ?? [];
            MutationValidationService::validateAndLockItems($type, $items, $sourceCampusId, $sourceLocationId);
            
            foreach ($items as $item) {
                if (!isset($item['inventory_balance_id']) || !isset($item['quantity'])) continue;
                $this->record->items()->create([
                    'inventory_balance_id' => $item['inventory_balance_id'],
                    'quantity' => $item['quantity']
                ]);
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
