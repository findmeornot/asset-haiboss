<?php

namespace App\Filament\Inventory\Widgets;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Location;
use App\Models\StockOpname;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Barang / Aset', Asset::count())
                ->description('Semua barang terdaftar')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary'),

            Stat::make('Mutasi Menunggu Persetujuan', AssetMovement::where('status', 'pending')->count())
                ->description('Perlu ditindaklanjuti')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('warning'),

            Stat::make('Stock Opname Berjalan', StockOpname::where('status', 'in_progress')->count())
                ->description('Sesi opname aktif')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color('info'),

            Stat::make('Total Lokasi', Location::count())
                ->description('Lokasi penyimpanan aset')
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('success'),
        ];
    }
}
