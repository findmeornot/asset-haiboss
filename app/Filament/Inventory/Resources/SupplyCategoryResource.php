<?php

namespace App\Filament\Inventory\Resources;

use App\Filament\Inventory\Resources\SupplyCategoryResource\Pages;
use App\Models\Asset;

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

    /**
     * Override: Edit BHP menggunakan halaman khusus EditSupplyCategory,
     * bukan AssetResource::edit yang umum (yang tidak mendukung BHP legacy).
     */
    public static function getEditUrl(Asset $record): string
    {
        return static::getUrl('edit', ['record' => $record]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupplyCategory::route('/'),
            'view'  => Pages\ViewSupplyCategory::route('/{record}'),
            'edit'  => Pages\EditSupplyCategory::route('/{record}/edit'),
        ];
    }
}
