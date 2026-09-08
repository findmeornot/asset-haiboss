<?php

namespace App\Filament\Inventory\Resources\SupplyCategoryResource\Pages;

use App\Filament\Inventory\Resources\SupplyCategoryResource;
use App\Filament\Inventory\Resources\Traits\HasCleanFilterUrls;
use Filament\Resources\Pages\ListRecords;

class ListSupplyCategory extends ListRecords
{
    use HasCleanFilterUrls;

    protected static string $resource = SupplyCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Inventory\Resources\Widgets\CategoryAssetStatsWidget::make([
                'categorySlug' => 'barang-habis-pakai',
            ]),
        ];
    }
}
