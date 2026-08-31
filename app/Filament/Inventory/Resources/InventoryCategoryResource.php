<?php

namespace App\Filament\Inventory\Resources;

use App\Filament\Inventory\Resources\InventoryCategoryResource\Pages;

/**
 * Menampilkan daftar barang berkategori "Inventaris" (type = inventory).
 */
class InventoryCategoryResource extends BaseCategoryAssetResource
{
    protected static string $categoryType = 'inventory';
    protected static ?string $slug = 'kategori-inventaris';
    protected static ?int $navigationSort = 3;

    public static function getNavigationIcon(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public static function getModelLabel(): string
    {
        return 'Inventaris';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Inventaris';
    }

    public static function getNavigationLabel(): string
    {
        return 'Inventaris';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryCategory::route('/'),
            'view'  => Pages\ViewInventoryCategory::route('/{record}'),
        ];
    }
}
