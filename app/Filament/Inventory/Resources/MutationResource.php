<?php
namespace App\Filament\Inventory\Resources;

use App\Filament\Inventory\Resources\MutationResource\Pages;
use App\Models\Mutation;
use App\Models\Asset;
use App\Models\InventoryBalance;
use Filament\Schemas\Schema;
use Filament\Forms\Components;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class MutationResource extends Resource
{
    protected static ?string $model = Mutation::class;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-arrows-right-left';
    }
    
    public static function getNavigationGroup(): string
    {
        return 'Asset Management';
    }

    public static function getModelLabel(): string
    {
        return 'Mutasi Barang';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Mutasi Barang';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                // Kolom Kiri
                SchemaComponents\Group::make()
                    ->schema([
                        SchemaComponents\Section::make('Informasi Mutasi')
                            ->schema([
                                Components\Select::make('type')
                                    ->label('Jenis Mutasi')
                                    ->options([
                                        'asset' => 'Aset',
                                        'inventory' => 'Inventaris',
                                        'consumable' => 'Barang Habis Pakai',
                                    ])
                                    ->native(false)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (callable $set) {
                                        $set('source_campus_id', null);
                                        $set('source_location_id', null);
                                        $set('asset_ids', []);
                                        $set('items', []);
                                    }),
                                
                                Components\Select::make('source_campus_id')
                                    ->label('Gedung Asal')
                                    ->relationship('sourceCampus', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->afterStateUpdated(function (callable $set) {
                                        $set('source_location_id', null);
                                        $set('asset_ids', []);
                                        $set('items', []);
                                    })
                                    ->required(),

                                Components\Select::make('source_location_id')
                                    ->label('Lokasi Asal')
                                    ->relationship('sourceLocation', 'name', fn ($query, $get) => $query->where('campus_id', $get('source_campus_id')))
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->afterStateUpdated(function (callable $set) {
                                        $set('asset_ids', []);
                                        $set('items', []);
                                    })
                                    ->disabled(fn ($get) => blank($get('source_campus_id')))
                                    ->required(),

                                Components\Select::make('source_pic_id')
                                    ->label('PIC Asal')
                                    ->relationship('sourcePic', 'name')
                                    ->searchable()
                                    ->preload(),
                            ])->columns(2),

                        SchemaComponents\Section::make('Tujuan Mutasi & Alasan')
                            ->schema([
                                Components\Select::make('destination_campus_id')
                                    ->label('Gedung Tujuan')
                                    ->relationship('destinationCampus', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->afterStateUpdated(fn (callable $set) => $set('destination_location_id', null))
                                    ->required(),

                                Components\Select::make('destination_location_id')
                                    ->label('Lokasi Tujuan')
                                    ->relationship('destinationLocation', 'name', fn ($query, $get) => $query->where('campus_id', $get('destination_campus_id')))
                                    ->searchable()
                                    ->preload()
                                    ->disabled(fn ($get) => blank($get('destination_campus_id')))
                                    ->required(),

                                Components\Select::make('destination_pic_id')
                                    ->label('PIC Tujuan')
                                    ->relationship('destinationPic', 'name')
                                    ->searchable()
                                    ->preload(),
                                    
                                Components\Textarea::make('reason')
                                    ->label('Alasan Mutasi')
                                    ->required()
                                    ->columnSpanFull(),
                            ])->columns(2),
                    ])->columnSpan(1),

                // Kolom Kanan
                SchemaComponents\Group::make()
                    ->schema([
                        SchemaComponents\Section::make('Daftar Barang')
                            ->schema([
                                Components\Select::make('asset_ids')
                                    ->label('Pilih Aset (Bisa Pilih Banyak)')
                                    ->multiple()
                                    ->optionsLimit(500)
                                    ->options(function (callable $get, ?Mutation $record) {
                                        $type = $get('type');
                                        if (!in_array($type, ['asset', 'inventory'])) return [];
                                        
                                        // HISTORICAL HYDRATION: If mutation is no longer pending, 
                                        // always fetch the specifically attached assets, ignoring their current location.
                                        if ($record && in_array($record->status, ['approved', 'completed', 'rejected'])) {
                                            $assetIds = $record->items()->whereNotNull('asset_id')->pluck('asset_id')->toArray();
                                            return Asset::whereIn('id', $assetIds)->get()->mapWithKeys(function ($asset) {
                                                $brand = $asset->brand ? ' - ' . $asset->brand : '';
                                                $label = $asset->name . $brand . ' (' . ($asset->barcode ?? $asset->inventory_number ?? 'No Barcode') . ')';
                                                return [$asset->id => $label];
                                            })->toArray();
                                        }
                                        
                                        $campusId = $get('source_campus_id');
                                        $locationId = $get('source_location_id');
                                        
                                        if (!$campusId || !$locationId) return [];
                                        
                                        $slug = ($type === 'asset') ? 'aset' : 'inventaris';
                                        
                                        return Asset::where('campus_id', $campusId)
                                            ->where('location_id', $locationId)
                                            ->whereHas('classification', fn($q) => $q->where('slug', $slug))
                                            ->whereIn('status', \App\Services\MutationValidationService::ELIGIBLE_STATUSES)
                                            ->get()
                                            ->mapWithKeys(function ($asset) {
                                                $brand = $asset->brand ? ' - ' . $asset->brand : '';
                                                $label = $asset->name . $brand . ' (' . ($asset->barcode ?? $asset->inventory_number ?? 'No Barcode') . ')';
                                                return [$asset->id => $label];
                                            })->toArray();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required(fn (callable $get) => in_array($get('type'), ['asset', 'inventory']))
                                    ->visible(fn (callable $get) => in_array($get('type'), ['asset', 'inventory']))
                                    ->disabled(fn (callable $get) => blank($get('source_location_id')))
                                    ->dehydrated(false)
                                    ->reactive()
                                    ->afterStateHydrated(function (Components\Select $component, ?Mutation $record) {
                                        if ($record && in_array($record->type, ['asset', 'inventory'])) {
                                            $component->state($record->items()->whereNotNull('asset_id')->pluck('asset_id')->toArray());
                                        }
                                    }),

                                Components\Placeholder::make('selected_assets_list')
                                    ->label('Rincian Aset Terpilih')
                                    ->visible(fn (callable $get) => in_array($get('type'), ['asset', 'inventory']) && !empty($get('asset_ids')))
                                    ->content(function (callable $get) {
                                        $ids = $get('asset_ids');
                                        if (empty($ids)) return '-';
                                        
                                        $assets = Asset::whereIn('id', $ids)->get();
                                        $html = '<div style="background: rgba(128,128,128,0.05); padding: 10px; border-radius: 8px;">';
                                        $html .= '<ul style="list-style-type: decimal; margin-left: 20px; gap: 4px; display: flex; flex-direction: column;">';
                                        foreach ($assets as $asset) {
                                            $brand = $asset->brand ? ' - ' . $asset->brand : '';
                                            $label = $asset->name . $brand . ' (' . ($asset->barcode ?? $asset->inventory_number ?? 'No Barcode') . ')';
                                            $html .= '<li>' . $label . '</li>';
                                        }
                                        $html .= '</ul></div>';
                                        return new \Illuminate\Support\HtmlString($html);
                                    }),

                                Components\Repeater::make('items')
                                    ->relationship()
                                    ->label('Daftar Item')
                                    ->schema([
                                        Components\Select::make('inventory_balance_id')
                                            ->label('Pilih Barang')
                                            ->optionsLimit(500)
                                            ->options(function (callable $get, ?Mutation $record) {
                                                $type = $get('../../type');
                                                if ($type !== 'consumable') return [];
                                                
                                                if ($record && in_array($record->status, ['approved', 'completed', 'rejected'])) {
                                                    $balanceIds = $record->items()->whereNotNull('inventory_balance_id')->pluck('inventory_balance_id')->toArray();
                                                    return InventoryBalance::whereIn('id', $balanceIds)->get()->mapWithKeys(function ($inv) {
                                                        $brand = $inv->brand ? ' - ' . $inv->brand : '';
                                                        $label = $inv->name . $brand . ' (Histori)';
                                                        return [$inv->id => $label];
                                                    })->toArray();
                                                }
                                                
                                                $campusId = $get('../../source_campus_id');
                                                $locationId = $get('../../source_location_id');
                                                
                                                if (!$campusId || !$locationId) return [];
                                                
                                                $query = InventoryBalance::where('campus_id', $campusId)
                                                    ->where('location_id', $locationId)
                                                    ->where('quantity', '>', 0);
                                                    
                                                return $query->get()->mapWithKeys(function ($inv) {
                                                    $brand = $inv->brand ? ' - ' . $inv->brand : '';
                                                    $label = $inv->name . $brand . ' (Stok: ' . $inv->quantity . ')';
                                                    return [$inv->id => $label];
                                                })->toArray();
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->reactive()
                                            ->required(fn (callable $get) => $get('../../type') === 'consumable')
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                            
                                        Components\TextInput::make('quantity')
                                            ->label('Jumlah Mutasi')
                                            ->numeric()
                                            ->default(1)
                                            ->minValue(1)
                                            ->maxValue(function (callable $get) {
                                                $invId = $get('inventory_balance_id');
                                                if (!$invId) return null;
                                                $inv = InventoryBalance::find($invId);
                                                return $inv ? $inv->quantity : null;
                                            })
                                            ->required(fn (callable $get) => $get('../../type') === 'consumable'),
                                    ])
                                    ->columns(2)
                                    ->minItems(1)
                                    ->required(fn (callable $get) => $get('type') === 'consumable')
                                    ->visible(fn (callable $get) => $get('type') === 'consumable')
                                    ->disabled(fn (callable $get) => blank($get('source_location_id'))),
                            ]),
                    ])->columnSpan(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('mutation_number')
                    ->label('No. Mutasi')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'asset' => 'Aset',
                        'inventory' => 'Inventaris',
                        'consumable' => 'Barang Habis Pakai',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'asset' => 'primary',
                        'inventory' => 'success',
                        'consumable' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('sourceCampus.name')
                    ->label('Dari Gedung'),
                Tables\Columns\TextColumn::make('destinationCampus.name')
                    ->label('Ke Gedung'),
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
                Tables\Columns\TextColumn::make('mutation_date')
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
                    ->visible(fn (Mutation $record) => $record->status === 'pending' && Auth::user()->hasPermissionTo('movements.approve'))
                    ->requiresConfirmation()
                    ->action(function (Mutation $record) {
                        try {
                            \Illuminate\Support\Facades\DB::transaction(function () use ($record) {
                                $lockedRecord = Mutation::where('id', $record->id)->lockForUpdate()->first();
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
                    ->visible(fn (Mutation $record) => $record->status === 'pending' && Auth::user()->hasPermissionTo('movements.approve'))
                    ->requiresConfirmation()
                    ->form([
                        \Filament\Forms\Components\Textarea::make('reject_reason')
                            ->label('Alasan Penolakan')
                            ->required(),
                    ])
                    ->action(function (Mutation $record, array $data) {
                        try {
                            \Illuminate\Support\Facades\DB::transaction(function () use ($record, $data) {
                                $lockedRecord = Mutation::where('id', $record->id)->lockForUpdate()->first();
                                if ($lockedRecord->status !== 'pending') {
                                    throw new \Exception('Mutasi ini sudah diproses.');
                                }
                                request()->merge(['reject_reason' => $data['reject_reason']]);
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
                    ->visible(fn (Mutation $record) => $record->status === 'approved' && Auth::user()->hasPermissionTo('movements.complete'))
                    ->requiresConfirmation()
                    ->action(function (Mutation $record) {
                        try {
                            \Illuminate\Support\Facades\DB::transaction(function () use ($record) {
                                $lockedRecord = Mutation::where('id', $record->id)->lockForUpdate()->first();
                                if ($lockedRecord->status !== 'approved') {
                                    throw new \Exception('Hanya mutasi yang disetujui yang dapat diselesaikan.');
                                }

                                $lockedRecord->update([
                                    'status' => 'completed',
                                    'mutation_date' => now(),
                                ]);

                                $parentLog = \App\Models\AuditLog::where('auditable_type', get_class($lockedRecord))
                                    ->where('auditable_id', $lockedRecord->id)
                                    ->where('action', 'mutation_completed')
                                    ->latest('id')
                                    ->first();

                                if ($parentLog && class_exists(\App\Services\AuditLogger::class)) {
                                    \App\Services\AuditLogger::$currentParentId = $parentLog->id;
                                }

                                // Process each item
                                foreach ($lockedRecord->items as $item) {
                                    if (in_array($lockedRecord->type, ['asset', 'inventory']) && $item->asset_id) {
                                        $asset = Asset::where('id', $item->asset_id)->lockForUpdate()->first();
                                        if ($asset) {
                                            $asset->update([
                                                'campus_id' => $lockedRecord->destination_campus_id,
                                                'location_id' => $lockedRecord->destination_location_id,
                                                'pic_id' => $lockedRecord->destination_pic_id,
                                            ]);
                                        }
                                    } elseif ($lockedRecord->type === 'consumable' && $item->inventory_balance_id) {
                                        $sourceBalance = InventoryBalance::where('id', $item->inventory_balance_id)->lockForUpdate()->first();
                                        if (!$sourceBalance || $sourceBalance->quantity < $item->quantity) {
                                            throw new \Exception('Stok tidak mencukupi untuk item: ' . ($sourceBalance->name ?? 'Unknown'));
                                        }
                                        
                                        // Deduct from source
                                        $sourceBalance->quantity -= $item->quantity;
                                        $sourceBalance->save();
                                        
                                        // Find or create destination balance
                                        $destBalance = InventoryBalance::lockForUpdate()->firstOrCreate([
                                            'category_id' => $sourceBalance->category_id,
                                            'name' => $sourceBalance->name,
                                            'campus_id' => $lockedRecord->destination_campus_id,
                                            'location_id' => $lockedRecord->destination_location_id,
                                        ], [
                                            'quantity' => 0,
                                            'status' => $sourceBalance->status ?? 'stock',
                                            'kondisi' => $sourceBalance->kondisi ?? 'good',
                                            'pic_id' => $lockedRecord->destination_pic_id,
                                        ]);
                                        
                                        $destBalance->quantity += $item->quantity;
                                        $destBalance->save();
                                    }
                                }

                                if (class_exists(\App\Services\AuditLogger::class)) {
                                    \App\Services\AuditLogger::$currentParentId = null;
                                }
                            });
                            \Filament\Notifications\Notification::make()->title('Mutasi Selesai')->success()->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
                        }
                    }),
                \Filament\Actions\EditAction::make()
                    ->visible(fn (Mutation $record) => $record->status === 'pending'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMutations::route('/'),
            'create' => Pages\CreateMutation::route('/create'),
            'view' => Pages\ViewMutation::route('/{record}'),
            'edit' => Pages\EditMutation::route('/{record}/edit'),
        ];
    }
}
