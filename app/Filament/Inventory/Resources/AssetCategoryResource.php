<?php

namespace App\Filament\Inventory\Resources;

use App\Filament\Inventory\Resources\AssetCategoryResource\Pages;

/**
 * Menampilkan daftar barang berkategori "Aset" (type = asset).
 */
class AssetCategoryResource extends BaseCategoryAssetResource
{
    protected static string $categoryType = 'asset';

    protected static ?string $slug = 'kategori-aset';

    public static function getNavigationIcon(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return 'heroicon-o-cube';
    }

    public static function getModelLabel(): string
    {
        return 'Aset';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Aset';
    }

    public static function getNavigationLabel(): string
    {
        return 'Aset';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssetCategory::route('/'),
            'view'  => Pages\ViewAssetCategory::route('/{record}'),
        ];
    }
}
