<?php

namespace App\Filament\Inventory\Resources\SupplyCategoryResource\Pages;

use App\Filament\Inventory\Resources\SupplyCategoryResource;
use Filament\Resources\Pages\ListRecords;

class ListSupplyCategory extends ListRecords
{
    protected static string $resource = SupplyCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
