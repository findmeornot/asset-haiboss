<?php

namespace App\Filament\Inventory\Resources\AssetResource\Pages;

use App\Filament\Inventory\Resources\AssetResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewAsset extends ViewRecord
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
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

        if ($this->record->purchase) {
            $data['purchase_data'] = array_merge($data['purchase_data'], $this->record->purchase->toArray());
        }
        return $data;
    }
}
