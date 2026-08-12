<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Employee;
use App\Models\Location;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Aset', Asset::count())
                ->description('Semua aset terdaftar')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary'),

            Stat::make('Total Kategori', Category::count())
                ->description('Kategori aset & inventaris')
                ->descriptionIcon('heroicon-m-tag')
                ->color('success'),

            Stat::make('Total Lokasi', Location::count())
                ->description('Lokasi penyimpanan aset')
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('warning'),

            Stat::make('Total Karyawan', Employee::count())
                ->description('PIC pengelola aset')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),
        ];
    }
}
