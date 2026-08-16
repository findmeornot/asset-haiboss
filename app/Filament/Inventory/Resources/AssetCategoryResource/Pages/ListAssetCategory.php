<?php

namespace App\Filament\Inventory\Resources\AssetCategoryResource\Pages;

use App\Filament\Inventory\Resources\AssetCategoryResource;
use Filament\Resources\Pages\ListRecords;

class ListAssetCategory extends ListRecords
{
    protected static string $resource = AssetCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Inventory\Resources\Widgets\CategoryAssetStatsWidget::make([
                'categorySlug' => 'aset',
            ]),
        ];
    }
}
