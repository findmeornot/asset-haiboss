<?php

namespace App\Filament\Inventory\Resources\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CategoryAssetStatsWidget extends StatsOverviewWidget
{
    public string $categorySlug = 'aset';

    protected function getStats(): array
    {
        $slug = $this->categorySlug;

        $query = \App\Models\Asset::whereHas('classification', fn($q) => $q->where('slug', $slug));
        
        $totalItems = (clone $query)->count();
        $totalPrice = (clone $query)->leftJoin('asset_purchases', 'assets.id', '=', 'asset_purchases.asset_id')
                            ->sum('asset_purchases.total_price');

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
