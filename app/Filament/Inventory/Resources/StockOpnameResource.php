<?php
namespace App\Filament\Inventory\Resources;

use App\Filament\Inventory\Resources\StockOpnameResource\Pages;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Asset;
use Filament\Schemas\Schema;
use Filament\Forms\Components;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Filament\Schemas\Components\Section;

class StockOpnameResource extends Resource
{
    protected static ?string $model = StockOpname::class;

    public static function getNavigationIcon(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-clipboard-document-check';
    }
    
    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return 'Asset Management';
    }

    public static function getModelLabel(): string
    {
        return 'Stock Opname';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Informasi Stock Opname')
                    ->schema([
                        Components\TextInput::make('name')
                            ->label('Nama Kegiatan')
                            ->required()
                            ->maxLength(255),
                        Components\Select::make('campus_id')
                            ->label('Gedung')
                            ->relationship('campus', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Components\Select::make('location_id')
                            ->label('Lokasi Spesifik (Opsional)')
                            ->relationship('location', 'name')
                            ->searchable()
                            ->preload(),
                        Components\DatePicker::make('start_date')
                            ->label('Tanggal Mulai')
                            ->required()
                            ->default(now()),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Kegiatan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('campus.name')
                    ->label('Gedung'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'in_progress' => 'warning',
                        'completed' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'in_progress' => 'Berjalan',
                        'completed' => 'Selesai',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Tanggal Mulai')
                    ->date(),
            ])
            ->actions([
                \Filament\Actions\Action::make('start')
                    ->label('Mulai Pemeriksaan')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->visible(fn (StockOpname $record) => $record->status === 'draft')
                    ->requiresConfirmation()
                    ->action(function (StockOpname $record) {
                        try {
                            \Illuminate\Support\Facades\DB::transaction(function () use ($record) {
                                $lockedRecord = StockOpname::where('id', $record->id)->lockForUpdate()->first();
                                
                                if ($lockedRecord->status !== 'draft') {
                                    throw new \Exception('Sesi Stock Opname ini sudah berjalan atau selesai.');
                                }
                                
                                $lockedRecord->update(['status' => 'in_progress', 'start_date' => now()]);
                                
                                $query = Asset::where('campus_id', $lockedRecord->campus_id);
                                if ($lockedRecord->location_id) {
                                    $query->where('location_id', $lockedRecord->location_id);
                                }
                                
                                $now = now();
                                
                                $query->chunkById(500, function ($assets) use ($lockedRecord, $now) {
                                    $items = [];
                                    foreach ($assets as $asset) {
                                        $items[] = [
                                            'stock_opname_id' => $lockedRecord->id,
                                            'asset_id' => $asset->id,
                                            'expected_location_id' => $asset->location_id,
                                            'is_found' => false,
                                            'condition' => $asset->status,
                                            'location_id' => null,
                                            'actual_location_id' => null,
                                            'scanned_inventory_number' => null,
                                            'notes' => null,
                                            'checked_by' => null,
                                            'checked_at' => null,
                                            'created_at' => $now,
                                            'updated_at' => $now,
                                        ];
                                    }
                                    StockOpnameItem::insertOrIgnore($items);
                                });
                            });
                            \Filament\Notifications\Notification::make()->title('Pemeriksaan Dimulai')->success()->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
                        }
                    }),
                \Filament\Actions\Action::make('scan')
                    ->label('Scan Barcode')
                    ->icon('heroicon-o-bars-4')
                    ->color('primary')
                    ->visible(fn (StockOpname $record) => $record->status === 'in_progress')
                    ->url(fn (StockOpname $record) => route('filament.inventory.pages.stock-opname-scanner', ['opname' => $record->ulid])),
                \Filament\Actions\Action::make('complete')
                    ->label('Selesaikan')
                    ->icon('heroicon-o-check-circle')
                    ->color('primary')
                    ->visible(fn (StockOpname $record) => $record->status === 'in_progress')
                    ->requiresConfirmation()
                    ->action(function (StockOpname $record) {
                        try {
                            \Illuminate\Support\Facades\DB::transaction(function () use ($record) {
                                $lockedRecord = StockOpname::where('id', $record->id)->lockForUpdate()->first();
                                
                                if ($lockedRecord->status !== 'in_progress') {
                                    throw new \Exception('Sesi Stock Opname ini tidak sedang berjalan.');
                                }
                                
                                $lockedRecord->update([
                                    'status' => 'completed',
                                    'end_date' => now(),
                                ]);
                            });
                            \Filament\Notifications\Notification::make()->title('Sesi Diselesaikan')->success()->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
                        }
                    }),
                \Filament\Actions\EditAction::make()
                    ->visible(fn (StockOpname $record) => $record->status === 'draft'),
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
            'index' => Pages\ListStockOpnames::route('/'),
            'create' => Pages\CreateStockOpname::route('/create'),
            'view' => Pages\ViewStockOpname::route('/{record}'),
            'edit' => Pages\EditStockOpname::route('/{record}/edit'),
        ];
    }
}
