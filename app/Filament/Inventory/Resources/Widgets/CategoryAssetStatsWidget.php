<?php

namespace App\Filament\Inventory\Resources\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Asset;

class CategoryAssetStatsWidget extends StatsOverviewWidget
{
    public string $categorySlug = 'aset';

    protected function getStats(): array
    {
        $slug = $this->categorySlug;

        $query = Asset::whereHas('classification', fn($q) => $q->where('slug', $slug));

        $totalItems = (clone $query)->count();

        // Dual-path price calculation:
        // - New assets: use purchaseItem.unit_price (per-unit, correct)
        // - Legacy assets (no purchaseItem): fallback to asset_purchases.total_price
        $newArchPrice = (clone $query)
            ->whereHas('purchaseItem')
            ->join('purchase_items', 'assets.purchase_item_id', '=', 'purchase_items.id')
            ->sum('purchase_items.unit_price');

        $legacyPrice = (clone $query)
            ->whereNull('purchase_item_id')
            ->leftJoin('asset_purchases', 'assets.id', '=', 'asset_purchases.asset_id')
            ->sum('asset_purchases.total_price');

        $totalPrice = $newArchPrice + $legacyPrice;

        return [
            Stat::make('Total Barang', number_format($totalItems, 0, ',', '.'))
                ->description('Jumlah barang keseluruhan di kategori ini')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary'),

            Stat::make('Total Harga Perolehan', 'Rp ' . number_format($totalPrice, 0, ',', '.'))
                ->description('Total nilai aset di kategori ini')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
        ];
    }
}
