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
                    \Filament\Schemas\Components\Section::make('Identitas')
                        ->schema([
                            Components\TextInput::make('inventory_number')
                                ->label('Nomor Inventaris')
                                ->disabled()
                                ->dehydrated(false)
                                ->visibleOn(['edit', 'view'])
                                ->helperText('Nomor inventaris dibuat otomatis oleh sistem.'),
                            Components\TextInput::make('name')
                                ->label('Nama Barang')
                                ->required()
                                ->maxLength(255),
                            Components\Select::make('classification_id')
                                ->label('Klasifikasi Barang')
                                ->relationship('classification', 'name')
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn (callable $set) => $set('category_id', null)),
                            Components\Select::make('category_id')
                                ->label('Kategori Barang')
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
                                    ? 'Pilih klasifikasi terlebih dahulu'
                                    : 'Pilih kategori')
                                ->helperText(fn (callable $get) => blank($get('classification_id'))
                                    ? 'Pilih klasifikasi barang terlebih dahulu untuk membuka daftar kategori.'
                                    : null),
                            Components\TextInput::make('serial_number')
                                ->label('Nomor Seri')
                                ->maxLength(255),
                            Components\Select::make('ownership')
                                ->label('Kepemilikan')
                                ->options([
                                    'company' => 'Perusahaan',
                                    'grant' => 'Hibah',
                                    'loan' => 'Pinjaman',
                                ])
                                ->required(),
                        ])->columns(2),

                    \Filament\Schemas\Components\Section::make('Lokasi')
                        ->schema([
                            Components\Select::make('campus_id')
                                ->label('Kampus')
                                ->relationship('campus', 'name')
                                ->searchable()
                                ->preload()
                                ->createOptionForm([
                                    Components\TextInput::make('name')
                                        ->label('Nama Kampus')
                                        ->required(),
                                    Components\Textarea::make('address')
                                        ->label('Alamat'),
                                ]),
                            Components\Select::make('location_id')
                                ->label('Lokasi Detail')
                                ->relationship('location', 'name')
                                ->searchable()
                                ->preload()
                                ->createOptionForm([
                                    Components\Select::make('campus_id')
                                        ->label('Kampus')
                                        ->relationship('campus', 'name')
                                        ->required(),
                                    Components\TextInput::make('name')
                                        ->label('Nama Lokasi')
                                        ->required(),
                                ]),
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

                    \Filament\Schemas\Components\Section::make('Status & Catatan')
                        ->schema([
                            Components\Select::make('status')
                                ->label('Status Barang')
                                ->options([
                                    'stock' => 'Stok Tersedia',
                                    'active' => 'Aktif / Digunakan',
                                    'borrowed' => 'Dipinjam',
                                    'maintenance' => 'Dalam Perbaikan',
                                    'minor_damage' => 'Rusak Ringan',
                                    'major_damage' => 'Rusak Berat',
                                    'lost' => 'Hilang',
                                    'sold' => 'Terjual',
                                    'administratively_deleted' => 'Penghapusan Administratif',
                                    'destroyed' => 'Dimusnahkan',
                                ])
                                ->required()
                                ->default('stock')
                                ->disabled(fn (?Asset $record) => $record !== null)
                                ->helperText('Ubah status melalui Action khusus pada tabel atau halaman detail.'),
                            Components\Textarea::make('notes')
                                ->label('Catatan Tambahan')
                                ->columnSpanFull(),
                        ])->columns(2),
                ])->columnSpan(['lg' => 2]),

                \Filament\Schemas\Components\Group::make()->schema([
                    \Filament\Schemas\Components\Section::make('Pembelian')
                        ->schema([
                            Components\DatePicker::make('purchase_date')
                                ->label('Tanggal Pembelian'),
                            Components\TextInput::make('quantity')
                                ->label('Banyaknya Unit')
                                ->numeric()
                                ->default(1)
                                ->live(debounce: 500)
                                ->afterStateUpdated(function ($set, $get) {
                                    $qty = (string) ((int) $get('quantity') ?: 1);
                                    $price = (string) ($get('unit_price') ?: '0');
                                    $set('total_price', bcmul($price, $qty, 2));
                                }),
                            Components\TextInput::make('unit_price')
                                ->label('Harga per Unit')
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
                        ->statePath('purchase_data')
                        // Only user with financial.view can see the section
                        ->visible(fn () => Auth::user()->hasPermissionTo('financial.view'))
                        // Only user with financial.update can edit it
                        ->disabled(fn () => !Auth::user()->hasPermissionTo('financial.update')),

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
                        ->visible(fn (?Asset $record) => $record !== null && Auth::user()->hasPermissionTo('financial.view')),
                ])->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('inventory_number')
                    ->label('No. Inventaris')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Barang')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategori')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('serial_number')
                    ->label('No. Seri')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('location.name')
                    ->label('Lokasi')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pic.name')
                    ->label('PIC')
                    ->searchable(),
                // Protected Financial columns
                Tables\Columns\TextColumn::make('purchase.total_price')
                    ->label('Total Pembelian')
                    ->money('idr')
                    ->visible(fn () => Auth::user()->hasPermissionTo('financial.view'))
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name'),
                Tables\Filters\SelectFilter::make('location_id')
                    ->label('Lokasi')
                    ->relationship('location', 'name'),
                Tables\Filters\SelectFilter::make('pic_id')
                    ->label('PIC')
                    ->relationship('pic', 'name'),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'stock' => 'Stok Tersedia',
                        'active' => 'Aktif / Digunakan',
                        'borrowed' => 'Dipinjam',
                        'maintenance' => 'Dalam Perbaikan',
                        'minor_damage' => 'Rusak Ringan',
                        'major_damage' => 'Rusak Berat',
                        'lost' => 'Hilang',
                        'sold' => 'Terjual',
                        'administratively_deleted' => 'Penghapusan Administratif',
                        'destroyed' => 'Dimusnahkan',
                    ]),
                Tables\Filters\SelectFilter::make('ownership')
                    ->label('Kepemilikan')
                    ->options([
                        'company' => 'Perusahaan',
                        'grant' => 'Hibah',
                        'loan' => 'Pinjaman',
                    ]),
                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => Auth::user()->hasRole('superadmin')),
            ])
            ->actions([
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
                    ->label('Ubah Status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (?Asset $record) => Auth::user()->hasPermissionTo('status.update') && ($record ? !$record->trashed() : true))
                    ->form([
                        Components\Select::make('new_status')
                            ->label('Status Baru')
                            ->options([
                                'stock' => 'Stok Tersedia',
                                'active' => 'Aktif / Digunakan',
                                'borrowed' => 'Dipinjam',
                                'maintenance' => 'Dalam Perbaikan',
                                'minor_damage' => 'Rusak Ringan',
                                'major_damage' => 'Rusak Berat',
                                'lost' => 'Hilang (Butuh Approval)',
                                'sold' => 'Terjual',
                                'administratively_deleted' => 'Penghapusan Administratif',
                                'destroyed' => 'Dimusnahkan (Butuh Approval)',
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
                                        'status' => 'pending',
                                        'reason' => $reason,
                                        'payload' => json_encode([
                                            'asset_id' => $record->id,
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
                                    ->body('Perubahan ke status kritis membutuhkan persetujuan.')
                                    ->warning()
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title('Status Berhasil Diubah')
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
            'index' => Pages\ListAssets::route('/'),
            'create' => Pages\CreateAsset::route('/create'),
            'view' => Pages\ViewAsset::route('/{record}'),
            'edit' => Pages\EditAsset::route('/{record}/edit'),
        ];
    }
}
