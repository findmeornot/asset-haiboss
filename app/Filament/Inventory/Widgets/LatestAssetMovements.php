<?php

namespace App\Filament\Inventory\Widgets;

use App\Models\AssetMovement;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestAssetMovements extends TableWidget
{
    protected int | string | array $columnSpan = 2;

    protected static ?int $sort = 1;

    protected function getTableHeading(): string
    {
        return 'Mutasi Aset Terbaru';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AssetMovement::query()
                    ->latest('movement_date')
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('asset.name')
                    ->label('Barang / Aset')
                    ->searchable(),
                TextColumn::make('sourceLocation.name')
                    ->label('Dari')
                    ->placeholder('-'),
                TextColumn::make('destinationLocation.name')
                    ->label('Ke')
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'completed' => 'success',
                        'rejected', 'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('movement_date')
                    ->label('Tanggal')
                    ->dateTime('d M Y'),
            ])
            ->paginated(false);
    }
}
