<?php

namespace App\Filament\Inventory\Resources\InventoryCategoryResource\Pages;

use App\Filament\Inventory\Resources\InventoryCategoryResource;
use App\Filament\Inventory\Resources\Traits\HasCleanFilterUrls;
use Filament\Resources\Pages\ListRecords;

class ListInventoryCategory extends ListRecords
{
    use HasCleanFilterUrls;

    protected static string $resource = InventoryCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('create')
                ->label('Tambah Barang')
                ->icon('heroicon-o-plus')
                ->url(fn () => \App\Filament\Inventory\Resources\UnifiedItemResource::getUrl('create')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Inventory\Resources\Widgets\CategoryAssetStatsWidget::make([
                'categorySlug' => 'inventaris',
            ]),
        ];
    }
}
