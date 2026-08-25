<?php

namespace App\Filament\Inventory\Resources\InventoryBalanceResource\Pages;

use App\Filament\Inventory\Resources\InventoryBalanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInventoryBalances extends ListRecords
{
    protected static string $resource = InventoryBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
