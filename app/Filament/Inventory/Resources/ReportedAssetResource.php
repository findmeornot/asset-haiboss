<?php

namespace App\Filament\Inventory\Resources;

use App\Filament\Inventory\Resources\ReportedAssetResource\Pages;
use App\Models\Asset;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Daftar laporan "Barang Datang" yang belum diproses admin (status
 * baru_dilaporkan). Begitu admin melengkapi data lewat AssetResource::edit
 * dan mengubah status, barang otomatis hilang dari sini dan muncul di
 * Semua Barang (lihat UnifiedItem::booted()).
 */
class ReportedAssetResource extends Resource
{
    protected static ?string $model = Asset::class;
    protected static ?string $slug = 'baru-dilaporkan';
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', 'baru_dilaporkan');
    }

    public static function getNavigationIcon(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return 'heroicon-o-inbox-arrow-down';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Pengelolaan Barang';
    }

    public static function getModelLabel(): string
    {
        return 'Baru Dilaporkan';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Baru Dilaporkan';
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
                Tables\Columns\ImageColumn::make('foto_resi')
                    ->label('Foto Resi')
                    ->disk('public')
                    ->square(),
                Tables\Columns\TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->wrap()
                    ->limit(80)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('campus.name')
                    ->label('Gedung')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('location.name')
                    ->label('Ruangan (Lokasi)')
                    ->toggleable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('reportedBy.name')
                    ->label('Dilaporkan Oleh')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dilaporkan Pada')
                    ->dateTime('d M Y H:i')
                    ->toggleable()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
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
                            ->options(fn (callable $get) => \App\Models\Location::when($get('campus_id'), fn ($q) => $q->where('campus_id', $get('campus_id')))->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->disabled(fn (callable $get) => blank($get('campus_id'))),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['campus_id'], fn (Builder $query, $campusId): Builder => $query->where('campus_id', $campusId))
                            ->when($data['location_id'], fn (Builder $query, $locationId): Builder => $query->where('location_id', $locationId));
                    }),
            ])
            ->actions([
                \Filament\Actions\Action::make('complete')
                    ->label('Lengkapi')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Asset $record) => AssetResource::getUrl('edit', ['record' => $record]))
                    ->visible(fn (Asset $record) => \Illuminate\Support\Facades\Auth::user()->can('update', $record)),
                \Filament\Actions\ViewAction::make()
                    ->hiddenLabel(),
            ])
            ->emptyStateHeading('Belum ada laporan barang datang')
            ->emptyStateDescription('Laporan baru dari aplikasi mobile akan muncul di sini.')
            ->emptyStateIcon('heroicon-o-inbox');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReportedAssets::route('/'),
            'view' => Pages\ViewReportedAsset::route('/{record}'),
        ];
    }
}
