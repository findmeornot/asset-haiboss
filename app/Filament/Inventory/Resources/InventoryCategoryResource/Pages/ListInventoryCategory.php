<?php

namespace App\Filament\Inventory\Resources\InventoryCategoryResource\Pages;

use App\Filament\Inventory\Resources\InventoryCategoryResource;
use Filament\Resources\Pages\ListRecords;

class ListInventoryCategory extends ListRecords
{
    protected static string $resource = InventoryCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
