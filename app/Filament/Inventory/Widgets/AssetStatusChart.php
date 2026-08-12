<?php

namespace App\Filament\Inventory\Widgets;

use App\Models\Asset;
use Filament\Widgets\ChartWidget;

class AssetStatusChart extends ChartWidget
{
    protected ?string $heading = 'Aset per Status';

    protected int | string | array $columnSpan = 2;

    protected static ?int $sort = 1;

    protected function getMaxHeight(): ?string
    {
        return '260px';
    }

    protected static array $labels = [
        'stock' => 'Stok Tersedia',
        'active' => 'Aktif / Digunakan',
        'borrowed' => 'Dipinjam',
        'maintenance' => 'Dalam Perbaikan',
        'minor_damage' => 'Rusak Ringan',
        'major_damage' => 'Rusak Berat',
        'lost' => 'Hilang',
        'sold' => 'Terjual',
        'administratively_deleted' => 'Penghapusan Administratif',
        'destroyed' => 'Dimusnahkan',
    ];

    protected function getData(): array
    {
        $counts = Asset::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'datasets' => [
                [
                    'data' => $counts->values(),
                    'backgroundColor' => [
                        '#3b82f6', '#22c55e', '#f59e0b', '#a855f7',
                        '#eab308', '#ef4444', '#6b7280', '#0ea5e9',
                        '#78716c', '#1f2937',
                    ],
                ],
            ],
            'labels' => $counts->keys()->map(fn ($status) => static::$labels[$status] ?? $status)->values(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
