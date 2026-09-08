<?php

namespace App\Filament\Inventory\Resources;

use App\Filament\Inventory\Resources\SupplyCategoryResource\Pages;

/**
 * Menampilkan daftar barang berkategori "Barang Habis Pakai" (type = supply).
 */
class SupplyCategoryResource extends BaseCategoryAssetResource
{
    protected static bool $shouldRegisterNavigation = false;
    protected static string $categoryType = 'supply';

    protected static ?string $slug = 'kategori-barang-habis-pakai';

    public static function getNavigationIcon(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return 'heroicon-o-archive-box';
    }

    public static function getModelLabel(): string
    {
        return 'Barang Habis Pakai';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Barang Habis Pakai';
    }

    public static function getNavigationLabel(): string
    {
        return 'Barang Habis Pakai';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupplyCategory::route('/'),
            'view'  => Pages\ViewSupplyCategory::route('/{record}'),
        ];
    }
}
