<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PermissionResource\Pages;
use App\Models\Permission;
use Filament\Forms\Components;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    public static function getNavigationIcon(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-key';
    }

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return 'User & Access';
    }

    public static function getModelLabel(): string
    {
        return 'Permission';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Permissions';
    }

    public static function canAccess(): bool
    {
        return Auth::user()->hasPermissionTo('permissions.view');
    }

    public static function canCreate(): bool
    {
        return Auth::user()->hasPermissionTo('permissions.manage');
    }

    public static function canEdit(Model $record): bool
    {
        return Auth::user()->hasPermissionTo('permissions.manage');
    }

    public static function canDelete(Model $record): bool
    {
        return Auth::user()->hasPermissionTo('permissions.manage');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Detail Permission')
                    ->schema([
                        Components\TextInput::make('name')
                            ->label('Nama Permission')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('group.action')
                            ->helperText('Format: {group}.{action}, contoh: assets.view, users.create.')
                            ->rule('regex:/^[a-z0-9_]+\.[a-z0-9_]+$/'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Permission')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('group')
                    ->label('Group')
                    ->state(fn (Permission $record): string => Str::headline(Str::before($record->name, '.')))
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('roles_count')
                    ->counts('roles')
                    ->label('Digunakan di Role'),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->emptyStateHeading('Belum ada Permission')
            ->emptyStateDescription('Buat Permission pertama Anda dengan menekan tombol di atas.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPermissions::route('/'),
            'create' => Pages\CreatePermission::route('/create'),
            'edit' => Pages\EditPermission::route('/{record}/edit'),
        ];
    }
}
