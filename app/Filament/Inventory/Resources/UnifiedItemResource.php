<?php

namespace App\Filament\Inventory\Resources;

use App\Filament\Inventory\Resources\UnifiedItemResource\Pages;
use App\Models\UnifiedItem;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UnifiedItemResource extends Resource
{
    protected static ?string $model = UnifiedItem::class;
    protected static ?string $slug = 'semua-barang';
    protected static ?int $navigationSort = 1;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-squares-2x2';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'PENGELOLAAN BARANG';
    }

    public static function getModelLabel(): string
    {
        return 'Semua Barang';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Semua Barang';
    }

    public static function form(Schema $schema): Schema
    {
        return \App\Filament\Inventory\Resources\AssetResource::form($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('barcode')
                    ->label('Barcode')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('item_name')
                    ->label('Nama Barang')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('brand')
                    ->label('Merk/Tipe')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category_name')
                    ->label('Kategori')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('classification_name')
                    ->label('Klasifikasi')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('QTY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('location_name')
                    ->label('Lokasi')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Harga Perolehan')
                    ->money('idr')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'stock'                    => 'Stok (Gudang)',
                        'active'                   => 'Aktif / Digunakan',
                        'borrowed'                 => 'Dipinjam',
                        'maintenance'              => 'Dalam Perbaikan',
                        'lost'                     => 'Hilang',
                        'sold'                     => 'Terjual',
                        'disposed'                 => 'Dihapuskan / Musnah',
                        'administratively_deleted' => 'Penghapusan Administratif',
                        'destroyed'                => 'Dimusnahkan',
                        default                    => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'stock', 'active'         => 'success',
                        'borrowed', 'maintenance' => 'warning',
                        'lost', 'destroyed'       => 'danger',
                        'sold', 'disposed', 'administratively_deleted' => 'gray',
                        default                   => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('kondisi')
                    ->label('Kondisi')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'good'         => 'Baik',
                        'minor_damage' => 'Rusak Ringan',
                        'major_damage' => 'Rusak Berat',
                        default        => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'good'         => 'success',
                        'minor_damage' => 'warning',
                        'major_damage' => 'danger',
                        default        => 'gray',
                    })
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('category_name')
                    ->label('Kategori')
                    ->options(fn () => \App\Models\Category::pluck('name', 'name')->toArray())
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'stock'                    => 'Stok (Gudang)',
                        'available'                => 'Tersedia',
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
                                function (\Illuminate\Database\Eloquent\Builder $query, $campusId) {
                                    $locationNames = \App\Models\Location::where('campus_id', $campusId)->pluck('name')->toArray();
                                    return $query->whereIn('location_name', $locationNames);
                                }
                            )
                            ->when(
                                $data['location_id'],
                                function (\Illuminate\Database\Eloquent\Builder $query, $locationId) {
                                    $location = \App\Models\Location::find($locationId);
                                    return $query->where('location_name', $location?->name);
                                }
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
                \Filament\Actions\Action::make('manage')
                    ->label('Kelola / Detail')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->url(function (UnifiedItem $record) {
                        if ($record->row_type === 'supply') {
                            return \App\Filament\Inventory\Resources\InventoryBalanceResource::getUrl('index', ['tableSearch' => $record->item_name]);
                        } else {
                            if (strtolower($record->classification_name) === 'aset') {
                                return \App\Filament\Inventory\Resources\AssetCategoryResource::getUrl('view', ['record' => $record->route_key]);
                            } else {
                                return \App\Filament\Inventory\Resources\InventoryCategoryResource::getUrl('view', ['record' => $record->route_key]);
                            }
                        }
                    })
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUnifiedItems::route('/'),
            'create' => Pages\CreateUnifiedItem::route('/create'),
        ];
    }
}
