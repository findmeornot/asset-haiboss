<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Schemas\Schema;
use Filament\Forms\Components;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    public static function getNavigationIcon(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-shield-check';
    }
    
    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return 'System Administration';
    }

    public static function getModelLabel(): string
    {
        return 'Audit Log';
    }

    // Completely disable creation, editing, deleting
    public static function canCreate(): bool { return false; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return false; }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\TextInput::make('action')->label('Tindakan'),
                Components\TextInput::make('auditable_type')->label('Tipe Modul'),
                Components\TextInput::make('auditable_id')->label('ID Modul'),
                Components\Textarea::make('reason')->label('Alasan'),
                Components\TextInput::make('ip_address')->label('IP Address'),
                Components\KeyValue::make('old_values')->label('Data Lama')->formatStateUsing(fn($state) => is_string($state) ? json_decode($state, true) : ($state ?? [])),
                Components\KeyValue::make('new_values')->label('Data Baru')->formatStateUsing(fn($state) => is_string($state) ? json_decode($state, true) : ($state ?? [])),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Waktu')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('User')->searchable(),
                Tables\Columns\TextColumn::make('action')->label('Tindakan')->badge(),
                Tables\Columns\TextColumn::make('auditable_type')->label('Modul')->searchable(),
                Tables\Columns\TextColumn::make('reason')->label('Alasan')->limit(30),
                Tables\Columns\TextColumn::make('ip_address')->label('IP'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
            'view' => Pages\ViewAuditLog::route('/{record}'),
        ];
    }
}
