<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CampusResource\Pages;
use App\Models\Campus;
use Filament\Schemas\Schema;
use Filament\Forms\Components;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CampusResource extends Resource
{
    protected static ?string $model = Campus::class;

    public static function getNavigationIcon(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-building-library';
    }

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return 'Master Data';
    }

    public static function getModelLabel(): string
    {
        return 'Campus';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Campuses';
    }

    
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Informasi Gedung')
                    ->schema([
                        Components\TextInput::make('name')
                            ->label('Nama Gedung')
                            ->required()
                            ->maxLength(255),
                        Components\TextInput::make('code')
                            ->label('Kode Gedung')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Components\Textarea::make('address')
                            ->label('Alamat')
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
            ->emptyStateHeading('Belum ada Campuses')
            ->emptyStateDescription('Buat Campus pertama Anda dengan menekan tombol di atas.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampuses::route('/'),
            'create' => Pages\CreateCampus::route('/create'),
            'view' => Pages\ViewCampus::route('/{record}'),
            'edit' => Pages\EditCampus::route('/{record}/edit'),
        ];
    }
}
