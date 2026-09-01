<?php

namespace App\Filament\Inventory\Resources;

use App\Filament\Inventory\Resources\AssetResource\Pages;
use App\Models\Asset;
use Filament\Schemas\Schema;
use Filament\Forms\Components;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AssetResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $model = Asset::class;

    public static function getNavigationIcon(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-cube';
    }

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return 'Asset Management';
    }

    public static function getModelLabel(): string
    {
        return 'Barang / Aset';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Barang / Aset';
    }

    public static function form(Schema $schema): Schema
    {
        $classifications = \Illuminate\Support\Facades\Cache::remember('classifications_slug_map', 3600, function () {
            return \App\Models\Classification::pluck('slug', 'id')->toArray();
        });
        
        $getSlug = function (callable $get) use ($classifications) {
            $id = $get('classification_id');
            return $id ? ($classifications[$id] ?? null) : null;
        };
        $isPersediaan = fn (callable $get) => strtolower($getSlug($get) ?? '') === 'persediaan-barang';
        $isAset = fn (callable $get) => strtolower($getSlug($get) ?? '') === 'aset';
        $isInventaris = fn (callable $get) => strtolower($getSlug($get) ?? '') === 'inventaris';

        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Klasifikasi Barang')
                    ->description('Pilih klasifikasi barang terlebih dahulu untuk menyesuaikan formulir.')
                    ->schema([
                        Components\ToggleButtons::make('classification_id')
                            ->hiddenLabel()
                            ->options(\App\Models\Classification::pluck('name', 'id'))
                            ->inline()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (callable $set) => $set('category_id', null))
                            ->disabled(fn ($record) => $record !== null),
                    ]),

                \Filament\Schemas\Components\Section::make('Identitas Barang')
                    ->schema([
                        Components\Select::make('category_id')
                            ->label('Kategori')
                            ->relationship(
                                name: 'category',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, callable $get) => $get('classification_id')
                                    ? $query->whereHas('classifications', fn ($q) => $q->whereKey($get('classification_id')))
                                    : $query->whereRaw('1 = 0'),
                            )
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Components\TextInput::make('name')->label('Nama Kategori')->required(),
                                Components\TextInput::make('code')->label('Kode Kategori')->unique('categories', 'code'),
                            ])
                            ->createOptionUsing(function (array $data, callable $get) {
                                $category = \App\Models\Category::create($data);
                                if ($classificationId = $get('classification_id')) {
                                    $category->classifications()->attach($classificationId);
                                }
                                return $category->getKey();
                            })
                            ->required()
                            ->columnSpan(1),

                        Components\TextInput::make('name')
                            ->label('Nama Barang')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(1),

                        Components\Select::make('brand')
                            ->label('Merk/Tipe')
                            ->searchable()
                            ->options(function (callable $get) {
                                $options = \App\Models\Asset::whereNotNull('brand')->distinct()->pluck('brand', 'brand')->toArray();
                                $current = $get('brand');
                                if ($current && !isset($options[$current])) $options[$current] = $current;
                                return $options;
                            })
                            ->createOptionForm([
                                Components\TextInput::make('brand')->label('Merk/Tipe Baru')->required()
                            ])
                            ->createOptionUsing(fn (array $data) => $data['brand'])
                            ->visible(fn (callable $get) => !$isPersediaan($get))
                            ->columnSpan(['default' => 1, 'md' => 2]),
                    ])
                    ->columns(['default' => 1, 'md' => 2])
                    ->visible(fn (callable $get) => filled($get('classification_id'))),

                \Filament\Schemas\Components\Section::make('Pembelian')
                    ->schema([
                        Components\TextInput::make('unit_price')
                            ->label('Harga Satuan')
                            ->numeric()
                            ->prefix('Rp')
                            ->nullable()
                            ->live(onBlur: true)
                            ->disabled(fn ($record) => $record && $record->purchaseItem && $record->purchaseItem->unit_price !== null)
                            ->afterStateUpdated(function ($set, $get) {
                                $qty = (int) $get('quantity') ?: 1;
                                $price = $get('unit_price') !== null ? (float) $get('unit_price') : null;
                                $set('total_price', $price !== null ? $price * $qty : null);
                            }),

                        Components\TextInput::make('quantity')
                            ->label('Jumlah')
                            ->numeric()
                            ->default(1)
                            ->required()
                            ->live(onBlur: true)
                            ->disabled(fn ($record) => $record !== null)
                            ->afterStateUpdated(function ($set, $get) {
                                $qty = (int) $get('quantity') ?: 1;
                                $price = $get('unit_price') !== null ? (float) $get('unit_price') : null;
                                $set('total_price', $price !== null ? $price * $qty : null);
                            }),
                            
                        Components\Select::make('unit')
                            ->label('Satuan')
                            ->options([
                                'Unit' => 'Unit', 'Pcs' => 'Pcs', 'Set' => 'Set',
                                'Kg' => 'Kg', 'Paket' => 'Paket', 'Lembar' => 'Lembar',
                                'Buah' => 'Buah', 'Meter' => 'Meter', 'Liter' => 'Liter',
                            ])
                            ->searchable()
                            ->native(false),
                            
                        Components\TextInput::make('total_price')
                            ->label('Total Harga')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated(),

                        Components\DatePicker::make('purchase_date')
                            ->label('Tahun Perolehan')
                            ->displayFormat('Y')
                            ->format('Y-m-d')
                            ->native(false),

                        Components\Select::make('ownership')
                            ->label('Sumber Dana')
                            ->options([
                                'company' => 'Yayasan',
                                'grant'   => 'Hibah',
                                'loan'    => 'Pinjaman',
                            ])
                            ->native(false)
                            ->required()
                            ->default('company'),
                    ])
                    ->columns(['default' => 1, 'md' => 2])
                    ->statePath('purchase_data')
                    ->visible(fn (callable $get) => filled($get('classification_id'))),

                \Filament\Schemas\Components\Section::make('Penempatan')
                    ->schema([
                        Components\Select::make('campus_id')
                            ->label('Gedung')
                            ->relationship('campus', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->afterStateUpdated(fn (callable $set) => $set('location_id', null))
                            ->createOptionForm([
                                Components\TextInput::make('name')->label('Nama Gedung')->required(),
                                Components\Textarea::make('address')->label('Alamat'),
                            ]),

                        Components\Select::make('location_id')
                            ->label('Ruangan')
                            ->relationship(
                                name: 'location',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, callable $get) => $get('campus_id')
                                    ? $query->where('campus_id', $get('campus_id'))
                                    : $query->whereRaw('1 = 0')
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn (callable $get) => blank($get('campus_id')))
                            ->placeholder(fn (callable $get) => blank($get('campus_id')) ? 'Pilih gedung terlebih dahulu' : 'Pilih ruangan...')
                            ->createOptionForm([
                                Components\Select::make('campus_id')
                                    ->label('Gedung')
                                    ->relationship('campus', 'name')
                                    ->required()
                                    ->default(fn (callable $get) => $get('../../campus_id')),
                                Components\TextInput::make('name')->label('Nama Ruangan')->required(),
                            ]),
                    ])
                    ->columns(['default' => 1, 'md' => 2])
                    ->visible(fn (callable $get) => filled($get('classification_id'))),

                \Filament\Schemas\Components\Section::make('Detail Fisik & Spesifikasi')
                    ->schema([
                        Components\TextInput::make('serial_number')
                            ->label('Nomor Seri')
                            ->maxLength(255)
                            ->hidden(fn (callable $get) => ((int) ($get('purchase_data.quantity') ?: 1)) > 1 || $isPersediaan($get)),

                        Components\Select::make('pic_id')
                            ->label('Penanggung Jawab (PIC)')
                            ->relationship('pic', 'name')
                            ->searchable()
                            ->preload()
                            ->hidden(fn (callable $get) => ((int) ($get('purchase_data.quantity') ?: 1)) > 1),

                        Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'stock'        => 'Stok (Gudang)',
                                'active'       => 'Aktif / Digunakan',
                                'borrowed'     => 'Dipinjam',
                                'maintenance'  => 'Dalam Perbaikan',
                                'lost'         => 'Hilang',
                                'sold'         => 'Terjual',
                                'disposed'     => 'Dihapuskan / Musnah',
                            ])
                            ->required()
                            ->default('stock')
                            ->native(false),

                        Components\Select::make('kondisi')
                            ->label('Kondisi')
                            ->options([
                                'good'         => 'Baik',
                                'minor_damage' => 'Rusak Ringan',
                                'major_damage' => 'Rusak Berat',
                            ])
                            ->required()
                            ->default('good')
                            ->native(false),
                            
                        Components\Textarea::make('notes')
                            ->label('Keterangan')
                            ->columnSpanFull(),

                        Components\FileUpload::make('asset_photos')
                            ->label('Foto Barang')
                            ->multiple()
                            ->maxFiles(3)
                            ->image()
                            ->imageEditor()
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('2000')
                            ->imageResizeTargetHeight('2000')
                            ->maxSize(5120) // 5MB limit per file
                            ->directory('asset-photos')
                            ->panelLayout('grid')
                            ->appendFiles()
                            ->formatStateUsing(function ($record) {
                                if (! $record) return [];
                                return $record->photos->sortBy('sort_order')->pluck('file_path')->toArray();
                            })
                            ->saveRelationshipsUsing(function ($component, $state, $record) {
                                $existingPaths = $record->photos->pluck('file_path')->toArray();
                                $newPaths = array_values($state ?? []);
                                $deletedPaths = array_diff($existingPaths, $newPaths);
                                foreach ($deletedPaths as $path) {
                                    $record->photos()->where('file_path', $path)->first()?->delete();
                                }
                                $addedPaths = array_diff($newPaths, $existingPaths);
                                foreach ($addedPaths as $path) {
                                    $disk = \Illuminate\Support\Facades\Storage::disk('public');
                                    $record->photos()->create([
                                        'file_path' => $path,
                                        'file_size' => $disk->exists($path) ? $disk->size($path) : null,
                                        'mime_type' => $disk->exists($path) ? $disk->mimeType($path) : null,
                                    ]);
                                }
                                foreach ($newPaths as $index => $path) {
                                    $record->photos()->where('file_path', $path)->update(['sort_order' => $index]);
                                }
                            })
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'md' => 2])
                    ->visible(fn (callable $get) => filled($get('classification_id'))),

                \Filament\Schemas\Components\Section::make('Informasi Akuntansi')
                    ->schema([
                        Components\Placeholder::make('capitalization_status')
                            ->label('Status Kapitalisasi')
                            ->content(function (callable $get, $record) {
                                $price = 0;
                                if ($record instanceof \App\Models\Asset && $record->purchaseItem) {
                                    $price = (float) $record->purchaseItem->unit_price;
                                } else {
                                    // Handle string with commas/dots if typed by user
                                    $rawPrice = $get('../../purchase_data/unit_price') ?? $get('purchase_data.unit_price') ?? 0;
                                    $price = (float) preg_replace('/[^0-9.]/', '', $rawPrice);
                                }

                                $threshold = \App\Models\PurchaseItem::CAPITALIZATION_THRESHOLD;
                                $formattedThreshold = 'Rp ' . number_format($threshold, 0, ',', '.');
                                $formattedPrice = 'Rp ' . number_format($price, 0, ',', '.');

                                if ($price >= $threshold) {
                                    return new \Illuminate\Support\HtmlString('<span style="color: green; font-weight: bold;">Dikapitalisasi (Aset Tetap)</span><br><small>Harga satuan (' . $formattedPrice . ') memenuhi batas kapitalisasi ' . $formattedThreshold . '.</small>');
                                }
                                return new \Illuminate\Support\HtmlString('<span style="color: orange; font-weight: bold;">Tidak Dikapitalisasi</span><br><small>Harga satuan (' . $formattedPrice . ') di bawah batas kapitalisasi ' . $formattedThreshold . '.</small>');
                            })
                            ->columnSpanFull(),

                        Components\Select::make('useful_life')
                            ->label('Masa Manfaat (Tahun)')
                            ->options([
                                '4' => '4 Tahun',
                                '8' => '8 Tahun',
                                '16' => '16 Tahun',
                                '20' => '20 Tahun',
                            ])
                            ->placeholder(function (callable $get) {
                                if ($categoryId = $get('category_id')) {
                                    $cat = \App\Models\Category::find($categoryId);
                                    return $cat ? 'Bawaan Kategori (' . $cat->useful_life . ' Tahun)' : 'Pilih Masa Manfaat';
                                }
                                return 'Pilih Masa Manfaat';
                            })
                            ->helperText('Biarkan kosong untuk menggunakan masa manfaat bawaan dari Kategori.')
                            ->columnSpanFull()
                            ->native(false),

                        \Filament\Schemas\Components\Grid::make(3)
                            ->schema([
                                Components\Placeholder::make('acquisition_cost')
                                    ->label('Harga Perolehan (Cost)')
                                    ->content(fn ($record) => $record instanceof \App\Models\Asset ? 'Rp ' . number_format(\App\Services\DepreciationService::calculate($record)['acquisition_cost'], 0, ',', '.') : '-'),

                                Components\Placeholder::make('book_value')
                                    ->label('Nilai Buku Saat Ini')
                                    ->content(fn ($record) => $record instanceof \App\Models\Asset ? 'Rp ' . number_format(\App\Services\DepreciationService::calculate($record)['book_value'], 0, ',', '.') : '-'),

                                Components\Placeholder::make('annual_depreciation')
                                    ->label('Penyusutan Tahunan')
                                    ->content(fn ($record) => $record instanceof \App\Models\Asset ? 'Rp ' . number_format(\App\Services\DepreciationService::calculate($record)['annual_depreciation'], 0, ',', '.') : '-'),
                            ]),
                    ])
                    ->columns(1)
                    ->visible(fn (callable $get) => filled($get('classification_id')) && $isAset($get)),

                \Filament\Schemas\Components\Group::make()->schema([
                    Components\TextInput::make('inventory_number')->label('SKU / Kode')->disabled()->visibleOn(['edit', 'view']),
                    Components\TextInput::make('barcode')->label('Barcode Number')->disabled()->visibleOn(['edit', 'view']),
                ])->visible(fn ($record) => $record !== null),
            ])->columns(1);
    }    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('barcode')
                    ->label('Barcode')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('inventory_number')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('classification.name')
                    ->label('Kat. Akuntansi')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategori')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Barang')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('brand')
                    ->label('Merk/Tipe')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('serial_number')
                    ->label('No. Seri')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'stock'                    => 'Stok (Gudang)',
                        'active'                   => 'Aktif / Digunakan',
                        'borrowed'                 => 'Dipinjam',
                        'maintenance'              => 'Dalam Perbaikan',
                        'lost'                     => 'Hilang',
                        'sold'                     => 'Terjual',
                        'disposed'                 => 'Dihapuskan / Musnah',
                        'administratively_deleted' => 'Pghps. Administratif',
                        'destroyed'                => 'Dimusnahkan',
                        default                    => $state,
                    }),
                Tables\Columns\TextColumn::make('kondisi')
                    ->label('Kondisi')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'good'                     => 'Baik',
                        'minor_damage'             => 'Rusak Ringan',
                        'major_damage'             => 'Rusak Berat',
                        default                    => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'good'         => 'success',
                        'minor_damage' => 'warning',
                        'major_damage' => 'danger',
                        default        => 'gray',
                    }),
                Tables\Columns\TextColumn::make('campus.name')
                    ->label('Gedung')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('location.name')
                    ->label('Ruangan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pic.name')
                    ->label('PIC')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                // Protected Financial columns — dual-path: new arch uses purchaseItem.unit_price, legacy uses purchase.total_price
                Tables\Columns\TextColumn::make('purchaseItem.unit_price')
                    ->label('Harga Perolehan')
                    ->money('idr')
                    ->sortable()
                    ->getStateUsing(function ($record): ?string {
                        // New architecture: use unit_price from PurchaseItem
                        if ($record->purchaseItem) {
                            return $record->purchaseItem->unit_price;
                        }
                        // Legacy fallback: use total_price from AssetPurchase
                        return $record->purchase?->total_price;
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('classification_id')
                    ->label('Kategori Akuntansi')
                    ->relationship('classification', 'name'),
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name'),
                Tables\Filters\Filter::make('campus_location')
                    ->form([
                        Components\Select::make('campus_id')
                            ->label('Gedung')
                            ->options(\App\Models\Campus::pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (callable $set) => $set('location_id', null)),
                        Components\Select::make('location_id')
                            ->label('Ruangan')
                            ->options(fn (callable $get) => \App\Models\Location::when($get('campus_id'), fn($q) => $q->where('campus_id', $get('campus_id')))->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->disabled(fn (callable $get) => blank($get('campus_id'))),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when(
                                $data['campus_id'],
                                fn (\Illuminate\Database\Eloquent\Builder $query, $campusId): \Illuminate\Database\Eloquent\Builder => $query->where('campus_id', $campusId),
                            )
                            ->when(
                                $data['location_id'],
                                fn (\Illuminate\Database\Eloquent\Builder $query, $locationId): \Illuminate\Database\Eloquent\Builder => $query->where('location_id', $locationId),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['campus_id'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Gedung: ' . \App\Models\Campus::find($data['campus_id'])?->name)
                                ->removeField('campus_id');
                        }
                        if ($data['location_id'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Ruangan: ' . \App\Models\Location::find($data['location_id'])?->name)
                                ->removeField('location_id');
                        }
                        return $indicators;
                    }),
                Tables\Filters\SelectFilter::make('pic_id')
                    ->label('PIC')
                    ->relationship('pic', 'name'),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'stock'                    => 'Stok (Gudang)',
                        'active'                   => 'Aktif / Digunakan',
                        'borrowed'                 => 'Dipinjam',
                        'maintenance'              => 'Dalam Perbaikan',
                        'lost'                     => 'Hilang',
                        'sold'                     => 'Terjual',
                        'disposed'                 => 'Dihapuskan / Musnah',
                        'administratively_deleted' => 'Penghapusan Administratif',
                        'destroyed'                => 'Dimusnahkan',
                    ]),
                Tables\Filters\SelectFilter::make('kondisi')
                    ->label('Kondisi')
                    ->options([
                        'good'                     => 'Baik',
                        'minor_damage'             => 'Rusak Ringan',
                        'major_damage'             => 'Rusak Berat',
                    ]),
                Tables\Filters\SelectFilter::make('ownership')
                    ->label('Sumber Dana')
                    ->options([
                        'company' => 'Yayasan',
                        'grant'   => 'Hibah',
                        'loan'    => 'Pinjaman',
                    ]),
                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => Auth::user()->hasRole('superadmin')),
            ])
            ->actions([
                \Filament\Actions\ActionGroup::make([
                    \Filament\Actions\Action::make('printLabel')
                        ->label('Cetak Barcode')
                        ->icon('heroicon-o-bars-4')
                        ->color('success')
                        ->modalHeading('Preview Label Aset')
                        ->modalContent(fn (Asset $record) => view('filament.components.asset-label-preview', ['record' => $record]))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Tutup')
                        ->extraModalFooterActions([
                            \Filament\Actions\Action::make('print')
                                ->label('Print Sekarang')
                                ->color('primary')
                                ->icon('heroicon-o-printer')
                                ->url(fn (Asset $record) => route('asset.label.print', $record->id))
                                ->openUrlInNewTab(),
                        ]),
                    \Filament\Actions\Action::make('changeStatus')
                        ->label('Ubah Status/Kondisi')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(fn ($record) => Auth::user()->hasPermissionTo('status.update') && ($record instanceof \App\Models\Asset ? !$record->trashed() : true))
                        ->form([
                            Components\Select::make('new_status')
                                ->label('Status Baru')
                                ->options([
                                    'stock'                    => 'Stok (Gudang)',
                                    'active'                   => 'Aktif / Digunakan',
                                    'borrowed'                 => 'Dipinjam',
                                    'maintenance'              => 'Dalam Perbaikan',
                                    'lost'                     => 'Hilang (Butuh Approval)',
                                    'sold'                     => 'Terjual',
                                    'disposed'                 => 'Dihapuskan / Musnah',
                                    'administratively_deleted' => 'Penghapusan Administratif (Butuh Approval)',
                                    'destroyed'                => 'Dimusnahkan (Butuh Approval)',
                                ])
                                ->required()
                                ->default(fn ($record) => $record->status),
                            Components\Select::make('new_kondisi')
                                ->label('Kondisi Baru')
                                ->options([
                                    'good'                     => 'Baik',
                                    'minor_damage'             => 'Rusak Ringan',
                                    'major_damage'             => 'Rusak Berat',
                                ])
                                ->required()
                                ->default(fn ($record) => $record->kondisi),
                            Components\Textarea::make('reason')
                                ->label('Alasan Perubahan')
                                ->required()
                        ])
                        ->action(function (Asset $record, array $data) {
                            $newStatus = $data['new_status'];
                            $newKondisi = $data['new_kondisi'];
                            $reason = $data['reason'];
    
                            try {
                                \Illuminate\Support\Facades\DB::transaction(function () use ($record, $newStatus, $newKondisi, $reason) {
                                    $lockedAsset = Asset::where('id', $record->id)->lockForUpdate()->first();
    
                                    if (in_array($newStatus, ['lost', 'destroyed'])) {
                                        $hasPending = \App\Models\ApprovalRequest::where('status', 'pending')
                                            ->where('request_type', 'status_change')
                                            ->whereJsonContains('payload->asset_id', $record->id)
                                            ->exists();
    
                                        if ($hasPending) {
                                            throw new \Exception("Barang ini masih memiliki pengajuan yang menunggu persetujuan.");
                                        }
    
                                        \App\Models\ApprovalRequest::create([
                                            'request_type' => 'status_change',
                                            'requested_by' => Auth::id(),
                                            'status'       => 'pending',
                                            'reason'       => $reason,
                                            'payload'      => json_encode([
                                                'asset_id'    => $record->id,
                                                'new_status'  => $newStatus,
                                                'old_status'  => $lockedAsset->status,
                                                'new_kondisi' => $newKondisi,
                                                'old_kondisi' => $lockedAsset->kondisi,
                                            ])
                                        ]);
                                    } else {
                                        request()->merge(['status_change_reason' => $reason]);
                                        $lockedAsset->update([
                                            'status' => $newStatus,
                                            'kondisi' => $newKondisi,
                                        ]);
                                    }
                                });
    
                                if (in_array($newStatus, ['lost', 'destroyed'])) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Menunggu Persetujuan')
                                        ->body('Perubahan ke kondisi kritis membutuhkan persetujuan.')
                                        ->warning()
                                        ->send();
                                } else {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Kondisi Berhasil Diubah')
                                        ->success()
                                        ->send();
                                }
                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Gagal Memproses')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    \Filament\Actions\ViewAction::make(),
                    \Filament\Actions\EditAction::make(),
                    \Filament\Actions\DeleteAction::make()
                        ->modalHeading('Hapus Aset (Soft Delete)')
                        ->modalDescription('Aset akan dihapus dari daftar aktif, tetapi histori tetap tersimpan.'),
                    \Filament\Actions\ForceDeleteAction::make(),
                    \Filament\Actions\RestoreAction::make(),
                ])
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                    \Filament\Actions\ForceDeleteBulkAction::make(),
                    \Filament\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada Barang/Aset')
            ->emptyStateDescription('Mulai kelola inventaris Anda dengan menambahkan barang baru.');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAssets::route('/'),
            'create' => Pages\CreateAsset::route('/create'),
            'view'   => Pages\ViewAsset::route('/{record}'),
            'edit'   => Pages\EditAsset::route('/{record}/edit'),
        ];
    }
}
