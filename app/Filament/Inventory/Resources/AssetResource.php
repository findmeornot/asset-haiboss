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
        return $schema
            ->components([
                \Filament\Schemas\Components\Group::make()->schema([
                    \Filament\Schemas\Components\Section::make('Identitas Barang')
                        ->schema([
                            // Kode (inventory_number)
                            Components\TextInput::make('inventory_number')
                                ->label('SKU / Kode')
                                ->disabled()
                                ->dehydrated(false)
                                ->visibleOn(['edit', 'view'])
                                ->helperText('SKU inventaris dibuat otomatis.'),

                            // Barcode Number (barcode)
                            Components\TextInput::make('barcode')
                                ->label('Barcode Number')
                                ->disabled()
                                ->dehydrated(false)
                                ->visibleOn(['edit', 'view'])
                                ->helperText('Identitas fisik permanen barang.'),

                            // Kategori Akuntansi (classification_id)
                            Components\Select::make('classification_id')
                                ->label('Kategori Akuntansi')
                                ->relationship('classification', 'name')
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn (callable $set) => $set('category_id', null)),

                            // Kategori (category_id)
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
                                    Components\TextInput::make('name')
                                        ->label('Nama Kategori')
                                        ->required(),
                                    Components\TextInput::make('code')
                                        ->label('Kode Kategori')
                                        ->unique('categories', 'code'),
                                    Components\Select::make('type')
                                        ->label('Tipe Kategori')
                                        ->options(\App\Models\Category::TYPES)
                                        ->default('asset')
                                        ->required(),
                                ])
                                ->createOptionUsing(function (array $data, callable $get) {
                                    $category = \App\Models\Category::create($data);

                                    if ($classificationId = $get('classification_id')) {
                                        $category->classifications()->attach($classificationId);
                                    }

                                    return $category->getKey();
                                })
                                ->required()
                                ->disabled(fn (callable $get) => blank($get('classification_id')))
                                ->placeholder(fn (callable $get) => blank($get('classification_id'))
                                    ? 'Pilih kategori akuntansi terlebih dahulu'
                                    : 'Pilih kategori')
                                ->helperText(fn (callable $get) => blank($get('classification_id'))
                                    ? 'Pilih kategori akuntansi terlebih dahulu untuk membuka daftar kategori.'
                                    : null),

                            // Nama Barang (name)
                            Components\TextInput::make('name')
                                ->label('Nama Barang')
                                ->required()
                                ->maxLength(255),

                            // Merk/Tipe (brand) - field baru
                            Components\Select::make('brand')
                                ->label('Merk/Tipe')
                                ->searchable()
                                ->options(function (callable $get) {
                                    $options = \App\Models\Asset::whereNotNull('brand')
                                        ->distinct()
                                        ->pluck('brand', 'brand')
                                        ->toArray();
                                        
                                    $current = $get('brand');
                                    if ($current && !isset($options[$current])) {
                                        $options[$current] = $current;
                                    }
                                    
                                    return $options;
                                })
                                ->createOptionForm([
                                    Components\TextInput::make('brand')
                                        ->label('Merk/Tipe Baru')
                                        ->required()
                                ])
                                ->createOptionUsing(function (array $data) {
                                    return $data['brand'];
                                }),

                            // Nomor Seri (serial_number)
                            Components\TextInput::make('serial_number')
                                ->label('Nomor Seri')
                                ->maxLength(255),
                        ])->columns(2),

                    \Filament\Schemas\Components\Section::make('Foto Barang')
                        ->description('Opsional • Maks. 3 foto. Tambahkan foto barang untuk membantu identifikasi fisik.')
                        ->schema([
                            Components\FileUpload::make('asset_photos')
                                ->hiddenLabel()
                                ->multiple()
                                ->maxFiles(3)
                                ->image()
                                ->imageEditor()
                                ->imageResizeMode('contain')
                                ->imageResizeTargetWidth('2000')
                                ->imageResizeTargetHeight('2000')
                                ->maxSize(5120) // 5MB limit per file
                                ->directory('asset-photos')
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                ->helperText('Dukungan format: JPG, PNG, WebP (Maks 5MB per file). Anda dapat memilih beberapa foto sekaligus dari galeri atau kamera.')
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
                                        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
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
                        ]),

                    \Filament\Schemas\Components\Section::make('Lokasi')
                        ->schema([
                            // Gedung (campus_id) — di UI disebut "Gedung", di export menjadi "Wilayah"
                            Components\Select::make('campus_id')
                                ->label('Gedung')
                                ->relationship('campus', 'name')
                                ->searchable()
                                ->preload()
                                ->live()
                                ->required()
                                ->afterStateUpdated(fn (callable $set) => $set('location_id', null))
                                ->createOptionForm([
                                    Components\TextInput::make('name')
                                        ->label('Nama Gedung')
                                        ->required(),
                                    Components\Textarea::make('address')
                                        ->label('Alamat'),
                                ]),

                            // Ruangan (location_id) — hanya berasal dari Gedung yang dipilih
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
                                ->placeholder(fn (callable $get) => blank($get('campus_id'))
                                    ? 'Pilih gedung terlebih dahulu'
                                    : 'Pilih ruangan...')
                                ->helperText(fn (callable $get) => blank($get('campus_id'))
                                    ? 'Pilih gedung terlebih dahulu untuk memilih ruangan.'
                                    : null)
                                ->createOptionForm([
                                    Components\Select::make('campus_id')
                                        ->label('Gedung')
                                        ->relationship('campus', 'name')
                                        ->required()
                                        ->default(fn (callable $get) => $get('../../campus_id')),
                                    Components\TextInput::make('name')
                                        ->label('Nama Ruangan')
                                        ->required(),
                                ]),

                            // PIC (pic_id)
                            Components\Select::make('pic_id')
                                ->label('PIC (Penanggung Jawab)')
                                ->relationship('pic', 'name')
                                ->searchable()
                                ->preload()
                                ->createOptionForm([
                                    Components\TextInput::make('name')
                                        ->label('Nama Lengkap')
                                        ->required(),
                                    Components\TextInput::make('employee_code')
                                        ->label('Nomor Induk / Kode'),
                                    Components\TextInput::make('department')
                                        ->label('Departemen'),
                                ]),
                        ])->columns(3),

                    \Filament\Schemas\Components\Section::make('Kondisi & Catatan')
                        ->schema([
                            // Kondisi (status)
                            Components\Select::make('status')
                                ->label('Kondisi')
                                ->options([
                                    'stock'                    => 'Stok Tersedia',
                                    'active'                   => 'Aktif / Digunakan',
                                    'borrowed'                 => 'Dipinjam',
                                    'maintenance'              => 'Dalam Perbaikan',
                                    'minor_damage'             => 'Rusak Ringan',
                                    'major_damage'             => 'Rusak Berat',
                                    'lost'                     => 'Hilang',
                                    'sold'                     => 'Terjual',
                                    'administratively_deleted' => 'Penghapusan Administratif',
                                    'destroyed'                => 'Dimusnahkan',
                                ])
                                ->required()
                                ->default('stock')
                                ->disabled(fn (?Asset $record) => $record !== null)
                                ->helperText('Ubah kondisi melalui Action khusus pada tabel atau halaman detail.'),

                            // Keterangan (notes)
                            Components\Textarea::make('notes')
                                ->label('Keterangan')
                                ->columnSpanFull(),
                        ])->columns(2),
                ])->columnSpan(['lg' => 2]),

                \Filament\Schemas\Components\Group::make()->schema([
                    \Filament\Schemas\Components\Section::make('Pembelian')
                        ->schema([
                            // Sumber Dana (ownership) — Yayasan/Hibah
                            Components\Select::make('ownership')
                                ->label('Sumber Dana')
                                ->options([
                                    'company' => 'Yayasan',
                                    'grant'   => 'Hibah',
                                    'loan'    => 'Pinjaman',
                                ])
                                ->native(false)
                                ->required(),

                            // Tahun Perolehan (purchase_date)
                            Components\DatePicker::make('purchase_date')
                                ->label('Tahun Perolehan')
                                ->displayFormat('Y')
                                ->format('Y-m-d')
                                ->native(false),

                            // Jumlah (quantity)
                            Components\TextInput::make('quantity')
                                ->label('Jumlah')
                                ->numeric()
                                ->default(1)
                                ->live(debounce: 500)
                                ->afterStateUpdated(function ($set, $get) {
                                    $qty = (string) ((int) $get('quantity') ?: 1);
                                    $price = (string) ($get('unit_price') ?: '0');
                                    $set('total_price', bcmul($price, $qty, 2));
                                }),

                            // Satuan (unit) - field baru
                            Components\Select::make('unit')
                                ->label('Satuan')
                                ->options([
                                    'Unit'   => 'Unit',
                                    'Pcs'    => 'Pcs',
                                    'Set'    => 'Set',
                                    'Kg'     => 'Kg',
                                    'Paket'  => 'Paket',
                                    'Lembar' => 'Lembar',
                                    'Buah'   => 'Buah',
                                    'Meter'  => 'Meter',
                                    'Liter'  => 'Liter',
                                ])
                                ->searchable()
                                ->native(false),

                            // Harga Perolehan (unit_price & total_price)
                            Components\TextInput::make('unit_price')
                                ->label('Harga Perolehan (per Unit)')
                                ->numeric()
                                ->prefix('Rp')
                                ->live(debounce: 500)
                                ->afterStateUpdated(function ($set, $get) {
                                    $qty = (string) ((int) $get('quantity') ?: 1);
                                    $price = (string) ($get('unit_price') ?: '0');
                                    $set('total_price', bcmul($price, $qty, 2));
                                }),
                            Components\TextInput::make('total_price')
                                ->label('Total Harga')
                                ->numeric()
                                ->prefix('Rp'),
                        ])
                        ->statePath('purchase_data'),

                    \Filament\Schemas\Components\Section::make('Penyusutan & Nilai Buku')
                        ->schema([
                            Components\Placeholder::make('acquisition_cost')
                                ->label('Harga Perolehan (Cost)')
                                ->content(fn (?Asset $record) => $record ? 'Rp ' . number_format(\App\Services\DepreciationService::calculate($record)['acquisition_cost'], 0, ',', '.') : '-'),
                            Components\Placeholder::make('book_value')
                                ->label('Nilai Buku Saat Ini')
                                ->content(fn (?Asset $record) => $record ? 'Rp ' . number_format(\App\Services\DepreciationService::calculate($record)['book_value'], 0, ',', '.') : '-'),
                            Components\Placeholder::make('accumulated_depreciation')
                                ->label('Akumulasi Penyusutan')
                                ->content(fn (?Asset $record) => $record ? 'Rp ' . number_format(\App\Services\DepreciationService::calculate($record)['accumulated_depreciation'], 0, ',', '.') : '-'),
                            Components\Placeholder::make('annual_depreciation')
                                ->label('Penyusutan Tahunan')
                                ->content(fn (?Asset $record) => $record ? 'Rp ' . number_format(\App\Services\DepreciationService::calculate($record)['annual_depreciation'], 0, ',', '.') : '-'),
                            Components\Placeholder::make('useful_life')
                                ->label('Masa Manfaat')
                                ->content(fn (?Asset $record) => $record ? \App\Services\DepreciationService::calculate($record)['useful_life'] . ' Tahun' : '-'),
                            Components\Placeholder::make('remaining_useful_life')
                                ->label('Sisa Masa Manfaat')
                                ->content(fn (?Asset $record) => $record ? \App\Services\DepreciationService::calculate($record)['remaining_useful_life'] . ' Tahun' : '-'),
                        ])
                        ->columns(2)
                        ->visible(fn (?Asset $record) => $record !== null && \App\Services\DepreciationService::calculate($record)['is_depreciable']),
                ])->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
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
                    ->label('Kondisi')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'stock'                    => 'Stok Tersedia',
                        'active'                   => 'Aktif / Digunakan',
                        'borrowed'                 => 'Dipinjam',
                        'maintenance'              => 'Dalam Perbaikan',
                        'minor_damage'             => 'Rusak Ringan',
                        'major_damage'             => 'Rusak Berat',
                        'lost'                     => 'Hilang',
                        'sold'                     => 'Terjual',
                        'administratively_deleted' => 'Pghps. Administratif',
                        'destroyed'                => 'Dimusnahkan',
                        default                    => $state,
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
                    ->label('Kondisi')
                    ->options([
                        'stock'                    => 'Stok Tersedia',
                        'active'                   => 'Aktif / Digunakan',
                        'borrowed'                 => 'Dipinjam',
                        'maintenance'              => 'Dalam Perbaikan',
                        'minor_damage'             => 'Rusak Ringan',
                        'major_damage'             => 'Rusak Berat',
                        'lost'                     => 'Hilang',
                        'sold'                     => 'Terjual',
                        'administratively_deleted' => 'Penghapusan Administratif',
                        'destroyed'                => 'Dimusnahkan',
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
                        ->label('Ubah Kondisi')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(fn (?Asset $record) => Auth::user()->hasPermissionTo('status.update') && ($record ? !$record->trashed() : true))
                        ->form([
                            Components\Select::make('new_status')
                                ->label('Kondisi Baru')
                                ->options([
                                    'stock'                    => 'Stok Tersedia',
                                    'active'                   => 'Aktif / Digunakan',
                                    'borrowed'                 => 'Dipinjam',
                                    'maintenance'              => 'Dalam Perbaikan',
                                    'minor_damage'             => 'Rusak Ringan',
                                    'major_damage'             => 'Rusak Berat',
                                    'lost'                     => 'Hilang (Butuh Approval)',
                                    'sold'                     => 'Terjual',
                                    'administratively_deleted' => 'Penghapusan Administratif',
                                    'destroyed'                => 'Dimusnahkan (Butuh Approval)',
                                ])
                                ->required(),
                            Components\Textarea::make('reason')
                                ->label('Alasan Perubahan')
                                ->required()
                        ])
                        ->action(function (Asset $record, array $data) {
                            $newStatus = $data['new_status'];
                            $reason = $data['reason'];
    
                            try {
                                \Illuminate\Support\Facades\DB::transaction(function () use ($record, $newStatus, $reason) {
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
                                                'asset_id'   => $record->id,
                                                'new_status' => $newStatus,
                                                'old_status' => $lockedAsset->status
                                            ])
                                        ]);
                                    } else {
                                        request()->merge(['status_change_reason' => $reason]);
                                        $lockedAsset->update(['status' => $newStatus]);
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
