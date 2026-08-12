<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Models\Permission;
use App\Models\Role;
use Filament\Forms\Components;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    public static function getNavigationIcon(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-shield-check';
    }

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return 'User & Access';
    }

    public static function canAccess(): bool
    {
        return Auth::user()->hasPermissionTo('roles.view');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Informasi Role')
                    ->description('Tentukan nama role untuk mengelompokkan hak akses pengguna dalam sistem.')
                    ->schema([
                        Components\TextInput::make('name')
                            ->label('Nama Role')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('Contoh: Superadmin, Manager Inventaris, Staff Finance'),
                    ])
                    ->columnSpanFull(),

                \Filament\Schemas\Components\Section::make('Hak Akses (Permissions)')
                    ->description('Permission dikelompokkan per modul. Centang hak akses yang diberikan untuk role ini.')
                    ->schema(static::permissionChecklistSchema())
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function permissionGroups(): Collection
    {
        return Permission::all()->groupBy(fn (Permission $permission): string => Str::before($permission->name, '.'));
    }

    protected static function permissionGroupFieldName(string $group): string
    {
        return 'permission_group_' . $group;
    }

    public static function permissionChecklistSchema(): array
    {
        return static::permissionGroups()
            ->map(function (Collection $permissions, string $group) {
                $label = Str::headline($group);

                return \Filament\Schemas\Components\Section::make($label)
                    ->collapsible()
                    ->compact()
                    ->schema([
                        Components\CheckboxList::make(static::permissionGroupFieldName($group))
                            ->hiddenLabel()
                            ->options($permissions->pluck('name', 'id')->all())
                            ->columns(2)
                            ->bulkToggleable(),
                    ]);
            })
            ->values()
            ->all();
    }

    public static function permissionGroupDataFromRecord(Role $record): array
    {
        $selected = $record->permissions()->pluck('permissions.id')->all();

        return static::permissionGroups()
            ->mapWithKeys(fn (Collection $permissions, string $group): array => [
                static::permissionGroupFieldName($group) => $permissions->pluck('id')->intersect($selected)->values()->all(),
            ])
            ->all();
    }

    public static function extractPermissionIds(array $data): array
    {
        return collect($data)
            ->filter(fn ($value, $key): bool => str_starts_with($key, 'permission_group_'))
            ->flatten()
            ->unique()
            ->values()
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Role')
                    ->searchable(),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Jumlah Hak Akses'),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
