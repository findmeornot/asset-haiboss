<?php
namespace App\Filament\Resources;

use App\Filament\Resources\AssetMovementResource\Pages;
use App\Models\AssetMovement;
use App\Models\Asset;
use Filament\Schemas\Schema;
use Filament\Forms\Components;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AssetMovementResource extends Resource
{
    protected static ?string $model = AssetMovement::class;

    public static function getNavigationIcon(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-arrows-right-left';
    }
    
    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return 'Asset Management';
    }

    public static function getModelLabel(): string
    {
        return 'Mutasi Aset';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Informasi Aset & Tujuan Mutasi')
                    ->schema([
                        Components\Select::make('asset_id')
                            ->label('Aset yang Dimutasi')
                            ->relationship('asset', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (callable $set, $state) {
                                if ($state) {
                                    $asset = Asset::find($state);
                                    if ($asset) {
                                        $set('source_campus_id', $asset->campus_id);
                                        $set('source_location_id', $asset->location_id);
                                        $set('source_pic_id', $asset->pic_id);
                                    }
                                }
                            }),
                        
                        // Hidden original locations
                        Components\Hidden::make('source_campus_id'),
                        Components\Hidden::make('source_location_id'),
                        Components\Hidden::make('source_pic_id'),

                        Components\Select::make('destination_campus_id')
                            ->label('Kampus Tujuan')
                            ->relationship('destinationCampus', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Components\Select::make('destination_location_id')
                            ->label('Lokasi Tujuan')
                            ->relationship('destinationLocation', 'name')
                            ->searchable()
                            ->preload(),
                        Components\Select::make('destination_pic_id')
                            ->label('PIC Tujuan')
                            ->relationship('destinationPic', 'name')
                            ->searchable()
                            ->preload(),
                        
                        Components\Textarea::make('reason')
                            ->label('Alasan Mutasi')
                            ->required()
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('asset.name')
                    ->label('Aset')
                    ->searchable(),
                Tables\Columns\TextColumn::make('sourceCampus.name')
                    ->label('Dari Kampus'),
                Tables\Columns\TextColumn::make('destinationCampus.name')
                    ->label('Ke Kampus'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'completed' => 'success',
                        'rejected' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('movement_date')
                    ->label('Tgl Mutasi')
                    ->date(),
                Tables\Columns\TextColumn::make('requestedBy.name')
                    ->label('Pemohon'),
            ])
            ->actions([
                \Filament\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (AssetMovement $record) => $record->status === 'pending' && Auth::user()->hasPermissionTo('movements.approve'))
                    ->requiresConfirmation()
                    ->action(function (AssetMovement $record) {
                        try {
                            \Illuminate\Support\Facades\DB::transaction(function () use ($record) {
                                $lockedRecord = AssetMovement::where('id', $record->id)->lockForUpdate()->first();
                                if ($lockedRecord->status !== 'pending') {
                                    throw new \Exception('Mutasi ini sudah diproses.');
                                }
                                $lockedRecord->update([
                                    'status' => 'approved',
                                    'approved_by' => Auth::id(),
                                ]);
                            });
                            \Filament\Notifications\Notification::make()->title('Mutasi Disetujui')->success()->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
                        }
                    }),
                \Filament\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (AssetMovement $record) => $record->status === 'pending' && Auth::user()->hasPermissionTo('movements.approve'))
                    ->requiresConfirmation()
                    ->action(function (AssetMovement $record) {
                        try {
                            \Illuminate\Support\Facades\DB::transaction(function () use ($record) {
                                $lockedRecord = AssetMovement::where('id', $record->id)->lockForUpdate()->first();
                                if ($lockedRecord->status !== 'pending') {
                                    throw new \Exception('Mutasi ini sudah diproses.');
                                }
                                $lockedRecord->update([
                                    'status' => 'rejected',
                                    'approved_by' => Auth::id(),
                                ]);
                            });
                            \Filament\Notifications\Notification::make()->title('Mutasi Ditolak')->success()->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
                        }
                    }),
                \Filament\Actions\Action::make('complete')
                    ->label('Selesaikan (Pindah Fisik)')
                    ->icon('heroicon-o-flag')
                    ->color('primary')
                    ->visible(fn (AssetMovement $record) => $record->status === 'approved' && Auth::user()->hasPermissionTo('movements.complete'))
                    ->requiresConfirmation()
                    ->action(function (AssetMovement $record) {
                        try {
                            \Illuminate\Support\Facades\DB::transaction(function () use ($record) {
                                $lockedRecord = AssetMovement::where('id', $record->id)->lockForUpdate()->first();
                                if ($lockedRecord->status !== 'approved') {
                                    throw new \Exception('Hanya mutasi yang disetujui yang dapat diselesaikan.');
                                }
                                
                                $asset = Asset::where('id', $lockedRecord->asset_id)->lockForUpdate()->first();
                                if ($asset) {
                                    $asset->update([
                                        'campus_id' => $lockedRecord->destination_campus_id,
                                        'location_id' => $lockedRecord->destination_location_id,
                                        'pic_id' => $lockedRecord->destination_pic_id,
                                    ]);
                                }

                                $lockedRecord->update([
                                    'status' => 'completed',
                                    'movement_date' => now(),
                                ]);
                            });
                            \Filament\Notifications\Notification::make()->title('Mutasi Selesai')->success()->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
                        }
                    }),
                \Filament\Actions\Action::make('printBA')
                    ->label('Cetak BA')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->visible(fn (AssetMovement $record) => $record->status === 'completed')
                    ->url(fn (AssetMovement $record) => route('asset.movement.ba', $record->id))
                    ->openUrlInNewTab(),
                \Filament\Actions\EditAction::make()
                    ->visible(fn (AssetMovement $record) => $record->status === 'pending'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssetMovements::route('/'),
            'create' => Pages\CreateAssetMovement::route('/create'),
            'view' => Pages\ViewAssetMovement::route('/{record}'),
            'edit' => Pages\EditAssetMovement::route('/{record}/edit'),
        ];
    }
}
