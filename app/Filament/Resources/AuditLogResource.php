<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Forms\Components;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

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
        return 'Audit Trail';
    }

    public static function getNavigationLabel(): string
    {
        return 'Audit Trail';
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole('Superadmin') ?? false;
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return false; }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('parent_id')->with(['user']))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y, H:i:s')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Pengguna')
                    ->default(fn (AuditLog $record) => $record->metadata['credentials']['email'] ?? 'System/Unknown')
                    ->searchable(),
                Tables\Columns\TextColumn::make('action')
                    ->label('Aktivitas')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        str_contains($state->value ?? $state, 'created') || str_contains($state->value ?? $state, 'approved') || ($state->value ?? $state) === 'mutation_completed' => 'success',
                        str_contains($state->value ?? $state, 'deleted') || str_contains($state->value ?? $state, 'rejected') => 'danger',
                        str_contains($state->value ?? $state, 'updated') || str_contains($state->value ?? $state, 'change') => 'warning',
                        default => 'info',
                    })
                    ->formatStateUsing(fn ($state) => str($state->value ?? $state)->replace('_', ' ')->title()),
                Tables\Columns\TextColumn::make('object_summary')
                    ->label('Objek / Ringkasan')
                    ->state(function (AuditLog $record) {
                        if (isset($record->metadata['snapshot']['inventory_number'])) {
                            return current(array_filter([$record->metadata['snapshot']['inventory_number'], $record->metadata['snapshot']['asset_name'] ?? null])) ? implode(' - ', array_filter([$record->metadata['snapshot']['inventory_number'], $record->metadata['snapshot']['asset_name'] ?? null])) : '-';
                        }
                        return $record->auditable_type ? class_basename($record->auditable_type) . ' #' . $record->auditable_id : '-';
                    })
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->where('metadata->snapshot->inventory_number', 'like', "%{$search}%")
                              ->orWhere('metadata->snapshot->asset_name', 'like', "%{$search}%");
                    }),
                Tables\Columns\TextColumn::make('metadata.ip_address')
                    ->label('IP Address')
                    ->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('Dari Tanggal'),
                        \Filament\Forms\Components\DatePicker::make('to')->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date))
                            ->when($data['to'], fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date));
                    }),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Pengguna')
                    ->relationship('user', 'name')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('action')
                    ->label('Aktivitas')
                    ->options(collect(\App\Enums\AuditAction::cases())->mapWithKeys(fn($a) => [$a->value => $a->label()]))
                    ->searchable(),
                Tables\Filters\Filter::make('ip_address')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('ip')->label('IP Address'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => 
                        $query->when($data['ip'], fn (Builder $query, $ip): Builder => 
                            $query->whereJsonContains('metadata->ip_address', $ip)
                            ->orWhere('metadata->ip_address', 'like', "%{$ip}%")
                        )
                    ),
                Tables\Filters\Filter::make('asset')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('asset_name')->label('Asset Name'),
                        \Filament\Forms\Components\TextInput::make('inventory_number')->label('Inventory Number'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => 
                        $query->when($data['asset_name'], fn (Builder $query, $name): Builder => 
                            $query->where('metadata->snapshot->asset_name', 'like', "%{$name}%")
                        )->when($data['inventory_number'], fn (Builder $query, $inv): Builder => 
                            $query->where('metadata->snapshot->inventory_number', 'like', "%{$inv}%")
                        )
                    ),
                Tables\Filters\Filter::make('mutation_id')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('mutation_id')->label('Mutation ID'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => 
                        $query->when($data['mutation_id'], fn (Builder $query, $id): Builder => 
                            $query->where('metadata->mutation_id', $id)
                        )
                    ),
                Tables\Filters\Filter::make('batch_uuid')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('batch_uuid')->label('Batch ID / Correlation ID'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => 
                        $query->when($data['batch_uuid'], fn (Builder $query, $id): Builder => 
                            $query->where('batch_uuid', $id)
                        )
                    ),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make()->label('Lihat Detail')->modalHeading('Detail Audit Trail')->modalWidth('4xl'),
            ])
            ->emptyStateHeading('Belum ada aktivitas audit.');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Informasi Utama')
                    ->columns(3)
                    ->schema([
                        Components\TextInput::make('action')
                            ->label('Aktivitas')
                            ->formatStateUsing(fn ($state) => str($state->value ?? $state)->replace('_', ' ')->title()),
                        Components\DateTimePicker::make('created_at')
                            ->label('Waktu')
                            ->format('d M Y, H:i:s'),
                        Components\Placeholder::make('user_name')
                            ->label('Pengguna')
                            ->content(fn (AuditLog $record) => $record->user?->name ?? $record->metadata['credentials']['email'] ?? 'Unknown'),
                        Components\Placeholder::make('ip_address')
                            ->label('IP Address')
                            ->content(fn (AuditLog $record) => $record->metadata['ip_address'] ?? '-'),
                        Components\Placeholder::make('user_agent')
                            ->label('Device / Browser')
                            ->columnSpan(2)
                            ->content(fn (AuditLog $record) => $record->metadata['user_agent'] ?? '-'),
                    ]),
                
                \Filament\Schemas\Components\Section::make('Detail Objek / Aset')
                    ->columns(3)
                    ->visible(fn (AuditLog $record) => isset($record->metadata['snapshot']) || $record->auditable_type)
                    ->schema([
                        Components\TextInput::make('metadata.snapshot.asset_name')
                            ->label('Nama Aset')
                            ->visible(fn (AuditLog $record) => isset($record->metadata['snapshot']['asset_name'])),
                        Components\TextInput::make('metadata.snapshot.inventory_number')
                            ->label('Nomor Inventaris')
                            ->visible(fn (AuditLog $record) => isset($record->metadata['snapshot']['inventory_number'])),
                        Components\TextInput::make('metadata.snapshot.sku')
                            ->label('SKU')
                            ->visible(fn (AuditLog $record) => isset($record->metadata['snapshot']['sku'])),
                        Components\Placeholder::make('auditable')
                            ->label('Target Objek')
                            ->content(fn (AuditLog $record) => $record->auditable_type ? class_basename($record->auditable_type) . ' #' . $record->auditable_id : null)
                            ->visible(fn (AuditLog $record) => !isset($record->metadata['snapshot']['asset_name'])),
                    ]),

                \Filament\Schemas\Components\Section::make('Detail Mutasi & Approval')
                    ->columns(2)
                    ->visible(fn (AuditLog $record) => in_array($record->action->value, ['mutation_created', 'mutation_approved', 'mutation_rejected', 'mutation_completed']))
                    ->schema([
                        Components\TextInput::make('metadata.snapshot.requester')->label('Pemohon'),
                        Components\TextInput::make('metadata.snapshot.approver')->label('Approver / Rejector'),
                        Components\TextInput::make('metadata.snapshot.source_location')->label('Lokasi Asal'),
                        Components\TextInput::make('metadata.snapshot.destination_location')->label('Lokasi Tujuan'),
                        Components\TextInput::make('metadata.snapshot.source_pic')->label('PIC Asal'),
                        Components\TextInput::make('metadata.snapshot.destination_pic')->label('PIC Tujuan'),
                        Components\Textarea::make('reason')->label('Alasan / Catatan')->columnSpanFull(),
                    ]),

                \Filament\Schemas\Components\Section::make('Perubahan Data (Before / After)')
                    ->visible(fn (AuditLog $record) => $record->children()->exists() || !empty($record->old_values))
                    ->schema([
                        Components\ViewField::make('changes')
                            ->hiddenLabel()
                            ->view('filament.infolists.components.audit-changes')
                    ]),

                \Filament\Schemas\Components\Section::make('Technical Details')
                    ->collapsed()
                    ->schema([
                        Components\Placeholder::make('parent_id')
                            ->label('Parent ID')
                            ->content(fn (AuditLog $record) => $record->parent_id ?? '-'),
                        Components\Placeholder::make('batch_uuid')
                            ->label('Batch UUID')
                            ->content(fn (AuditLog $record) => $record->batch_uuid ?? '-'),
                        Components\Placeholder::make('metadata')
                            ->label('Raw Metadata')
                            ->content(fn (AuditLog $record) => empty($record->metadata) ? '-' : new \Illuminate\Support\HtmlString('<pre class="text-xs bg-gray-50 dark:bg-gray-900 p-2 rounded overflow-x-auto">' . json_encode($record->metadata, JSON_PRETTY_PRINT) . '</pre>'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }
}
