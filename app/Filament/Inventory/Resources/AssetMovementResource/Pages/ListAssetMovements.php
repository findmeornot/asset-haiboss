<?php
namespace App\Filament\Inventory\Resources\AssetMovementResource\Pages;
use App\Filament\Inventory\Resources\AssetMovementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
class ListAssetMovements extends ListRecords
{
    protected static string $resource = AssetMovementResource::class;
    protected function getHeaderActions(): array
    {
        return [ Actions\CreateAction::make() ];
    }
}
