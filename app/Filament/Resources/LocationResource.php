<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocationResource\Pages;
use App\Models\Location;
use Filament\Schemas\Schema;
use Filament\Forms\Components;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    public static function getNavigationIcon(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-map-pin';
    }

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return 'Master Data';
    }

    public static function getModelLabel(): string
    {
        return 'Ruangan';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Ruangan';
    }

    
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Informasi Lokasi')
                    ->schema([
                        Components\Select::make('campus_id')
                            ->label('Gedung')
                            ->relationship('campus', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Components\TextInput::make('name')
                            ->label('Nama Lokasi')
                            ->required()
                            ->maxLength(255),
                        Components\TextInput::make('code')
                            ->label('Kode Lokasi')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Components\Select::make('type')
                            ->label('Tipe')
                            ->options([
                                'Building' => 'Gedung',
                                'Room' => 'Ruangan',
                                'Area' => 'Area Bebas',
                            ]),
                        Components\Textarea::make('description')
                            ->label('Deskripsi')
                            ->columnSpanFull(),
                        Components\Toggle::make('active')
                            ->label('Aktif')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                
                Tables\Columns\TextColumn::make('campus.name')
                    ->label('Gedung')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->searchable(),
                Tables\Columns\IconColumn::make('active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Status Aktif')
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada Locations')
            ->emptyStateDescription('Buat Location pertama Anda dengan menekan tombol di atas.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'view' => Pages\ViewLocation::route('/{record}'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
        ];
    }
}
