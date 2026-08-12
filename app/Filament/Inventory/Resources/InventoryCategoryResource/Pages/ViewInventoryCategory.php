<?php

namespace App\Filament\Inventory\Resources\InventoryCategoryResource\Pages;

use App\Filament\Inventory\Resources\InventoryCategoryResource;
use App\Filament\Inventory\Resources\AssetResource;
use App\Models\Asset;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewInventoryCategory extends ViewRecord
{
    protected static string $resource = InventoryCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('editFull')
                ->label('Edit Barang')
                ->icon('heroicon-o-pencil-square')
                ->url(fn () => AssetResource::getUrl('edit', ['record' => $this->record]))
                ->visible(fn () => Auth::user()->can('update', $this->record)),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (Auth::user()->hasPermissionTo('financial.view') && $this->record->purchase) {
            $data['purchase_data'] = $this->record->purchase->toArray();
        }
        return $data;
    }
}
