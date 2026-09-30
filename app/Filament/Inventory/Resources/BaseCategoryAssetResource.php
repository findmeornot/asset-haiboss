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
            'supply' => 'barang-habis-pakai',
            default => static::$categoryType,
        };

        return parent::getEloquentQuery()
            ->whereHas('classification', fn (Builder $q) => $q->where('slug', $slug))
            ->whereNotIn('status', ['baru_dilaporkan', 'menunggu_pengecekan']);
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Pengelolaan Barang';
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
                    ->label('Kode Barang')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Barang')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('brand')
                    ->label('Merk/Tipe')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategori')
                    ->toggleable()
                    ->searchable()
                    ->sortable()
                    ->badge(),
                Tables\Columns\TextColumn::make('serial_number')
                    ->label('No. Seri')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->toggleable()
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'stock'                   => 'Stok (Gudang)',
                        'active'                  => 'Aktif / Digunakan',
                        'borrowed'                => 'Dipinjam',
                        'maintenance'             => 'Dalam Perbaikan',
                        'lost'                    => 'Hilang',
                        'sold'                    => 'Terjual',
                        'disposed'                => 'Dihapuskan / Musnah',
                        'administratively_deleted'=> 'Penghapusan Administratif',
                        'destroyed'               => 'Dimusnahkan',
                        default                   => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'stock'                   => 'info',
                        'active'                  => 'success',
                        'borrowed'                => 'warning',
                        'maintenance'             => 'warning',
                        'major_damage'            => 'danger',
                        'lost'                    => 'danger',
                        'sold'                    => 'gray',
                        'administratively_deleted'=> 'gray',
                        'destroyed'               => 'danger',
                        default                   => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('kondisi')
                    ->label('Kondisi')
                    ->toggleable()
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'good'                     => 'Baik',
                        'minor_damage'             => 'Rusak Ringan',
                        'major_damage'             => 'Rusak Berat',
                        default                    => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'good'         => 'success',
                        'minor_damage' => 'warning',
                        'major_damage' => 'danger',
                        default        => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('campus.name')
                    ->label('Gedung')
                    ->toggleable()
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
                    ->toggleable()
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
            ->defaultSort('created_at', 'desc')
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
                                'supply' => 'barang-habis-pakai',
                                default => static::$categoryType,
                            };
                            return $query->whereHas('classifications', fn($q) => $q->where('slug', $slug));
                        }
                    ),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'stock'                    => 'Stok (Gudang)',
                        'active'                   => 'Aktif / Digunakan',
                        'borrowed'                 => 'Dipinjam',
                        'maintenance'              => 'Dalam Perbaikan',
                        'lost'                     => 'Hilang',
                        'sold'                     => 'Terjual',
                        'disposed'                 => 'Dihapuskan / Musnah',
                        'administratively_deleted' => 'Penghapusan Administratif',
                        'destroyed'                => 'Dimusnahkan',
                    ]),
                Tables\Filters\SelectFilter::make('kondisi')
                    ->label('Kondisi')
                    ->options([
                        'good'                     => 'Baik',
                        'minor_damage'             => 'Rusak Ringan',
                        'major_damage'             => 'Rusak Berat',
                    ]),
                Tables\Filters\Filter::make('campus_location')
                    // Dropdown Gedung/Ruangan dirender di toolbar tabel (lihat
                    // AppServiceProvider); field hidden ini cuma nampung state-nya.
                    ->form([
                        \Filament\Forms\Components\Hidden::make('campus_id'),
                        \Filament\Forms\Components\Hidden::make('location_id'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when(
                                $data['campus_id'] ?? null,
                                fn (\Illuminate\Database\Eloquent\Builder $query, $campusId): \Illuminate\Database\Eloquent\Builder => $query->where('campus_id', $campusId),
                            )
                            ->when(
                                $data['location_id'] ?? null,
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
                \Filament\Actions\Action::make('editFull')
                    ->label('Edit')
                    ->hiddenLabel()
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn ($record) => AssetResource::getUrl('edit', ['record' => $record]))
                    ->visible(fn ($record) => Auth::user()->can('update', $record)),
                \App\Filament\Support\PrintBarcodeActions::viewAction()->hiddenLabel(),
                \App\Filament\Support\PrintBarcodeActions::rowAction(),
            ])
            // Centang tetap ada saat search/filter berubah. Tautan "Pilih semua N
            // data" dimatikan, karena tanpa filter-ulang ia bisa memilih seluruh tabel.
            ->deselectAllRecordsWhenFiltered(false)
            ->selectCurrentPageOnly()
            ->bulkActions([
                \App\Filament\Support\PrintBarcodeActions::bulkAction(),
                \App\Filament\Support\PrintChecklistActions::bulkAction(),
                \App\Filament\Support\MoveLocationActions::bulkAction(),
                \App\Filament\Support\ChangeCategoryActions::bulkAction(),
            ])
            ->emptyStateHeading('Belum ada barang di kategori ini')
            ->emptyStateDescription('Tambahkan barang baru dan pilih kategori yang sesuai.')
            ->emptyStateIcon('heroicon-o-inbox');
    }
}
