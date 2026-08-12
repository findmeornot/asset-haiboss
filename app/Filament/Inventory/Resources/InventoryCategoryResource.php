<?php

namespace App\Filament\Inventory\Resources;

use App\Filament\Inventory\Resources\InventoryCategoryResource\Pages;

/**
 * Menampilkan daftar barang berkategori "Inventoris" (type = inventory).
 */
class InventoryCategoryResource extends BaseCategoryAssetResource
{
    protected static string $categoryType = 'inventory';

    protected static ?string $slug = 'kategori-inventoris';

    public static function getNavigationIcon(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public static function getModelLabel(): string
    {
        return 'Inventoris';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Inventoris';
    }

    public static function getNavigationLabel(): string
    {
        return 'Inventoris';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryCategory::route('/'),
            'view'  => Pages\ViewInventoryCategory::route('/{record}'),
        ];
    }
}
