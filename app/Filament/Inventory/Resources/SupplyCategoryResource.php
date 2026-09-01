<?php

namespace App\Filament\Inventory\Resources;

use App\Filament\Inventory\Resources\SupplyCategoryResource\Pages;

/**
 * Menampilkan daftar barang berkategori "Persediaan" (type = supply).
 */
class SupplyCategoryResource extends BaseCategoryAssetResource
{
    protected static string $categoryType = 'supply';

    protected static ?string $slug = 'kategori-persediaan';

    public static function getNavigationIcon(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return 'heroicon-o-archive-box';
    }

    public static function getModelLabel(): string
    {
        return 'Persediaan';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Persediaan';
    }

    public static function getNavigationLabel(): string
    {
        return 'Persediaan';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupplyCategory::route('/'),
            'view'  => Pages\ViewSupplyCategory::route('/{record}'),
        ];
    }
}
