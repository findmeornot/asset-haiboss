<?php

namespace App\Filament\Inventory\Resources;

use App\Models\Asset;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Menampilkan daftar aset yang difilter berdasarkan tipe kategori.
 * Digunakan sebagai base class oleh AssetCategoryResource, InventoryCategoryResource, SupplyCategoryResource.
 */
abstract class BaseCategoryAssetResource extends Resource
{
    protected static ?string $model = Asset::class;

    /**
     * Tipe kategori yang akan digunakan untuk filter.
     * Override di subclass: 'asset', 'inventory', 'supply'
     */
    protected static string $categoryType = 'asset';

    public static function getEloquentQuery(): Builder
    {
        $slug = match (static::$categoryType) {
            'asset' => 'aset',
            'inventory' => 'inventaris',
            'supply' => 'persediaan-barang',
            default => static::$categoryType,
        };

        return parent::getEloquentQuery()
            ->whereHas('classification', fn (Builder $q) => $q->where('slug', $slug));
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Kategori';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return AssetResource::form($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('inventory_number')
                    ->label('No. Inventaris')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Barang')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategori')
                    ->searchable()
                    ->sortable()
                    ->badge(),
                Tables\Columns\TextColumn::make('serial_number')
                    ->label('No. Seri')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'stock'                   => 'Stok Tersedia',
                        'active'                  => 'Aktif / Digunakan',
                        'borrowed'                => 'Dipinjam',
                        'maintenance'             => 'Dalam Perbaikan',
                        'minor_damage'            => 'Rusak Ringan',
                        'major_damage'            => 'Rusak Berat',
                        'lost'                    => 'Hilang',
                        'sold'                    => 'Terjual',
                        'administratively_deleted'=> 'Penghapusan Administratif',
                        'destroyed'               => 'Dimusnahkan',
                        default                   => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'stock'                   => 'info',
                        'active'                  => 'success',
                        'borrowed'                => 'warning',
                        'maintenance'             => 'warning',
                        'minor_damage'            => 'warning',
                        'major_damage'            => 'danger',
                        'lost'                    => 'danger',
                        'sold'                    => 'gray',
                        'administratively_deleted'=> 'gray',
                        'destroyed'               => 'danger',
                        default                   => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('campus.name')
                    ->label('Gedung')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('location.name')
                    ->label('Ruangan (Lokasi)')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('pic.name')
                    ->label('PIC')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('purchaseItem.unit_price')
                    ->label('Harga Perolehan (per unit)')
                    ->money('idr')
                    ->visible(fn () => Auth::user()->hasPermissionTo('financial.view'))
                    ->sortable()
                    ->getStateUsing(function ($record): ?string {
                        // New architecture: use unit_price from PurchaseItem
                        if ($record->purchaseItem) {
                            return $record->purchaseItem->unit_price;
                        }
                        // Legacy fallback: use total_price from AssetPurchase
                        return $record->purchase?->total_price;
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship(
                        'category',
                        'name',
                        function (Builder $query) {
                            $slug = match (static::$categoryType) {
                                'asset' => 'aset',
                                'inventory' => 'inventaris',
                                'supply' => 'persediaan-barang',
                                default => static::$categoryType,
                            };
                            return $query->whereHas('classifications', fn($q) => $q->where('slug', $slug));
                        }
                    ),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'stock'                    => 'Stok Tersedia',
                        'active'                   => 'Aktif / Digunakan',
                        'borrowed'                 => 'Dipinjam',
                        'maintenance'              => 'Dalam Perbaikan',
                        'minor_damage'             => 'Rusak Ringan',
                        'major_damage'             => 'Rusak Berat',
                        'lost'                     => 'Hilang',
                        'sold'                     => 'Terjual',
                        'administratively_deleted' => 'Penghapusan Administratif',
                        'destroyed'                => 'Dimusnahkan',
                    ]),
                Tables\Filters\Filter::make('campus_location')
                    ->form([
                        \Filament\Forms\Components\Select::make('campus_id')
                            ->label('Gedung')
                            ->options(\App\Models\Campus::pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (callable $set) => $set('location_id', null)),
                        \Filament\Forms\Components\Select::make('location_id')
                            ->label('Ruangan (Lokasi)')
                            ->options(fn (callable $get) => \App\Models\Location::when($get('campus_id'), fn($q) => $q->where('campus_id', $get('campus_id')))->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->disabled(fn (callable $get) => blank($get('campus_id'))),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when(
                                $data['campus_id'],
                                fn (\Illuminate\Database\Eloquent\Builder $query, $campusId): \Illuminate\Database\Eloquent\Builder => $query->where('campus_id', $campusId),
                            )
                            ->when(
                                $data['location_id'],
                                fn (\Illuminate\Database\Eloquent\Builder $query, $locationId): \Illuminate\Database\Eloquent\Builder => $query->where('location_id', $locationId),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['campus_id'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Gedung: ' . \App\Models\Campus::find($data['campus_id'])?->name)
                                ->removeField('campus_id');
                        }
                        if ($data['location_id'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Ruangan: ' . \App\Models\Location::find($data['location_id'])?->name)
                                ->removeField('location_id');
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make()
                    ->hiddenLabel(),
            ])
            ->emptyStateHeading('Belum ada barang di kategori ini')
            ->emptyStateDescription('Tambahkan barang baru dan pilih kategori yang sesuai.')
            ->emptyStateIcon('heroicon-o-inbox');
    }
}
