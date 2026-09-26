<?php

namespace App\Filament\Inventory\Resources;

use App\Filament\Inventory\Resources\AntrianBarangMasukResource\Pages;
use App\Models\Asset;
use Filament\Actions;
use Filament\Forms\Components;
use Filament\Infolists\Components\ImageEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Antrian Finance untuk Asset hasil "Lapor Barang Datang" oleh OB
 * (lihat App\Http\Controllers\Api\BarangMasukController) yang masih
 * berstatus `baru_dilaporkan` dan belum dilengkapi identitas/pembelian.
 *
 * Sengaja TIDAK extends BaseCategoryAssetResource — resource itu filter
 * whereHas('classification'), sedangkan Asset hasil OB classification_id-nya
 * masih null sehingga akan selalu ter-exclude.
 *
 * Tahap ini hanya list + detail (placeholder). Wizard pelengkapan data,
 * penentuan classification otomatis, Purchase/PurchaseItem, dan transisi
 * status sengaja belum diimplementasikan di sini.
 */
class AntrianBarangMasukResource extends Resource
{
    protected static ?string $model = Asset::class;

    protected static ?int $navigationSort = 0;

    public static function getNavigationIcon(): string|Htmlable|null
    {
        return 'heroicon-o-inbox-arrow-down';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Pengelolaan Barang';
    }

    public static function getNavigationLabel(): string
    {
        return 'Antrian Barang Masuk';
    }

    /**
     * Badge jumlah antrian di sidebar — scope sama persis dengan tabel
     * (getEloquentQuery() + whereNull('purchase_item_id'), lihat komentar
     * di table() soal kenapa exclusion ini tidak ditaruh di getEloquentQuery()).
     */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->whereNull('purchase_item_id')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getModelLabel(): string
    {
        return 'Laporan Barang Masuk';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Antrian Barang Masuk';
    }

    /**
     * Sumber data cuma Asset hasil lapor OB yang belum diproses —
     * scope-nya sama persis dengan BarangMasukController::index().
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNotNull('reported_by')
            ->with(['campus', 'location', 'reportedBy']);
    }

    /**
     * "Lengkapi Data" khusus Finance — permission granular `intake.complete`,
     * BUKAN `assets.update` (itu tetap dipakai AssetResource & resource Asset
     * lain lewat AssetPolicy::update() seperti biasa, tidak diubah di sini).
     *
     * HARUS override getEditAuthorizationResponse(), bukan canEdit(): tombol
     * EditAction di ViewAntrianBarangMasuk diotorisasi lewat
     * Page::getDefaultActionAuthorizationResponse() yang manggil method ini
     * langsung (bukan canEdit()). Sedangkan canEdit() bawaan Filament sendiri
     * cuma wrapper getEditAuthorizationResponse($record)->allowed(), jadi
     * override di sini otomatis ikut membetulkan EditRecord::authorizeAccess()
     * (LengkapiBarangMasuk, route `/{record}/edit` langsung) juga — satu
     * titik utk kedua jalur.
     *
     * Selain permission, record yang `purchase_item_id`-nya sudah terisi
     * (sudah pernah dilengkapi Finance) ditolak juga di sini — bukan cuma
     * disembunyikan dari UI, tapi langsung di titik authorization yang sama
     * yang dipakai tombol EditAction maupun direct URL `/edit`.
     */
    public static function getEditAuthorizationResponse(Model $record): Response
    {
        if (! Auth::user()->hasPermissionTo('intake.complete')) {
            return Response::deny('Anda tidak memiliki izin untuk melengkapi data barang masuk ini.');
        }

        if (filled($record->purchase_item_id)) {
            return Response::deny('Barang ini sudah dilengkapi Finance dan tidak dapat diproses ulang.');
        }

        return Response::allow();
    }

    /**
     * Dipakai halaman View sebagai infolist read-only.
     *
     * Section pertama ("Laporan Awal") murni field dari laporan OB
     * (BarangMasukController::store), tidak berubah walau wizard sudah
     * dijalankan Finance — campus_id/location_id di Asset cuma satu kolom,
     * jadi field Gedung/Ruangan di sini otomatis ikut ke-update begitu
     * Finance ubah Penempatan di wizard (bukan snapshot laporan awal yang
     * beku, karena memang tidak ada kolom terpisah untuk itu).
     *
     * Section kedua ("Data Hasil Pelengkapan Finance") baca langsung dari
     * kolom Asset (name/brand/classification/category/inventory_number,
     * di-set oleh LengkapiBarangMasuk::handleRecordUpdate()) dan dari
     * relasi Asset::purchaseItem()/PurchaseItem::purchase() untuk data
     * pembelian (unit_price/quantity/unit/total_price/purchase_date/
     * ownership) — tidak ada model/query baru, murni reuse relasi existing.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Laporan Awal')
                    ->schema([
                        ImageEntry::make('foto_resi')
                            ->label('Foto Resi / Barang')
                            ->disk(config('filesystems.default'))
                            ->imageHeight(300)
                            ->placeholder('Tidak ada foto.')
                            ->url(fn (?string $state) => $state ? Storage::disk(config('filesystems.default'))->url($state) : null)
                            ->openUrlInNewTab()
                            ->columnSpanFull(),

                        Components\Placeholder::make('keterangan')
                            ->label('Keterangan')
                            ->content(fn (?Asset $record) => $record?->keterangan ?: '-')
                            ->columnSpanFull(),

                        Components\Placeholder::make('reported_by')
                            ->label('Dilaporkan Oleh')
                            ->content(fn (?Asset $record) => $record?->reportedBy?->name ?: '-'),

                        Components\Placeholder::make('created_at')
                            ->label('Tanggal Laporan')
                            ->content(fn (?Asset $record) => $record?->created_at?->format('d M Y H:i') ?: '-'),

                        Components\Placeholder::make('campus')
                            ->label('Gedung')
                            ->content(fn (?Asset $record) => $record?->campus?->name ?: '-'),

                        Components\Placeholder::make('location')
                            ->label('Ruangan')
                            ->content(fn (?Asset $record) => $record?->location?->name ?: '-'),

                        Components\Placeholder::make('status')
                            ->label('Status')
                            ->content(fn (?Asset $record) => match ($record?->status) {
                                'stock' => 'Stok (Gudang)',
                                'active' => 'Aktif / Digunakan',
                                'borrowed' => 'Dipinjam',
                                'maintenance' => 'Dalam Perbaikan',
                                'lost' => 'Hilang',
                                'sold' => 'Terjual',
                                'disposed' => 'Dihapuskan / Musnah',
                                'administratively_deleted' => 'Pghps. Administratif',
                                'destroyed' => 'Dimusnahkan',
                                'baru_dilaporkan' => 'Baru Dilaporkan',
                                'menunggu_pengecekan' => 'Menunggu Pengecekan',
                                null => '-',
                                default => $record->status,
                            }),
                    ])
                    ->columns(2),

                Section::make('Data Hasil Pelengkapan Finance')
                    ->schema([
                        Components\Placeholder::make('finance_completion_status')
                            ->hiddenLabel()
                            ->content(fn (?Asset $record) => $record?->purchase_item_id
                                ? 'Sudah dilengkapi Finance.'
                                : 'Belum dilengkapi Finance.')
                            ->columnSpanFull(),

                        Section::make('Identitas Barang')
                            ->schema([
                                Components\Placeholder::make('finance_name')
                                    ->label('Nama Barang')
                                    ->content(fn (?Asset $record) => $record?->name ?: '-'),

                                Components\Placeholder::make('finance_brand')
                                    ->label('Merk / Tipe')
                                    ->content(fn (?Asset $record) => $record?->brand ?: '-'),

                                Components\Placeholder::make('finance_classification')
                                    ->label('Klasifikasi')
                                    ->content(fn (?Asset $record) => $record?->classification?->name ?: '-'),

                                Components\Placeholder::make('finance_category')
                                    ->label('Kategori')
                                    ->content(fn (?Asset $record) => $record?->category?->name ?: '-'),
                            ])
                            ->columns(2),

                        Section::make('Pembelian')
                            ->schema([
                                Components\Placeholder::make('finance_unit_price')
                                    ->label('Harga Satuan')
                                    ->content(fn (?Asset $record) => $record?->purchaseItem?->unit_price !== null
                                        ? 'Rp '.number_format((float) $record->purchaseItem->unit_price, 0, ',', '.')
                                        : '-'),

                                Components\Placeholder::make('finance_quantity')
                                    ->label('Jumlah')
                                    ->content(fn (?Asset $record) => $record?->purchaseItem?->quantity ?? '-'),

                                Components\Placeholder::make('finance_unit')
                                    ->label('Satuan')
                                    ->content(fn (?Asset $record) => $record?->purchaseItem?->unit ?: '-'),

                                Components\Placeholder::make('finance_total_price')
                                    ->label('Total Harga')
                                    ->content(fn (?Asset $record) => $record?->purchaseItem?->total_price !== null
                                        ? 'Rp '.number_format((float) $record->purchaseItem->total_price, 0, ',', '.')
                                        : '-'),

                                Components\Placeholder::make('finance_purchase_date')
                                    ->label('Tahun Pembelian')
                                    ->content(fn (?Asset $record) => $record?->purchaseItem?->purchase?->purchase_date?->format('Y') ?: '-'),

                                Components\Placeholder::make('finance_ownership')
                                    ->label('Sumber Dana')
                                    ->content(fn (?Asset $record) => match ($record?->purchaseItem?->purchase?->ownership) {
                                        'company' => 'Yayasan',
                                        'grant' => 'Hibah',
                                        'loan' => 'Pinjaman',
                                        default => '-',
                                    }),
                            ])
                            ->columns(3),

                        Section::make('Penempatan')
                            ->schema([
                                Components\Placeholder::make('finance_campus')
                                    ->label('Gedung')
                                    ->content(fn (?Asset $record) => $record?->campus?->name ?: '-'),

                                Components\Placeholder::make('finance_location')
                                    ->label('Ruangan')
                                    ->content(fn (?Asset $record) => $record?->location?->name ?: '-'),

                                Components\Placeholder::make('finance_inventory_number')
                                    ->label('Nomor Inventaris')
                                    ->content(fn (?Asset $record) => $record?->inventory_number ?: '-'),
                            ])
                            ->columns(3),
                    ]),
            ])
            ->columns(1);
    }

    /**
     * Exclusion whereNull/whereNotNull('purchase_item_id') TIDAK ditaruh di
     * getEloquentQuery() — method itu juga dipakai route binding View/Edit
     * (resolveRecordRouteBinding() -> getRecordRouteBindingEloquentQuery()
     * default-nya getEloquentQuery()). Kalau exclusion ditaruh di sana,
     * record jadi 404 pas dibuka lewat View. Filter Menunggu/Selesai
     * ditaruh per-Tab di ListAntrianBarangMasuk::getTabs(), bukan di sini.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'asc')
            ->contentGrid([
                'default' => 1,
                'sm' => 2,
                'lg' => 3,
                'xl' => 4,
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::AfterContent)
            ->filters([
                Tables\Filters\SelectFilter::make('campus_id')
                    ->label('Gedung')
                    ->relationship('campus', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('location_id')
                    ->label('Ruangan')
                    ->relationship('location', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('reported_by')
                    ->label('Pelapor')
                    ->relationship('reportedBy', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->columns([
                Tables\Columns\ImageColumn::make('foto_resi')
                    ->label('Foto')
                    ->disk(config('filesystems.default'))
                    ->height(120)
                    ->extraImgAttributes(['class' => 'w-full object-cover rounded-lg'])
                    ->placeholder('Tidak ada foto.'),

                Tables\Columns\TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->searchable()
                    ->limit(60)
                    ->wrap(),

                Tables\Columns\TextColumn::make('reportedBy.name')
                    ->label('Pelapor')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal Laporan')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('campus.name')
                    ->label('Gedung')
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('location.name')
                    ->label('Ruangan')
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'baru_dilaporkan' => 'Baru Dilaporkan',
                        'menunggu_pengecekan' => 'Menunggu Pengecekan',
                        'administratively_deleted' => 'Pghps. Administratif',
                        'destroyed' => 'Dimusnahkan',
                        default => 'Selesai',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'baru_dilaporkan' => 'primary',
                        'menunggu_pengecekan' => 'warning',
                        'administratively_deleted', 'destroyed' => 'danger',
                        default => 'success',
                    }),
            ])
            ->recordActions([
                Actions\EditAction::make()
                    ->label('Lengkapi Data'),

                Actions\ViewAction::make()
                    ->label('Detail')
                    ->color('gray'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAntrianBarangMasuk::route('/'),
            'view' => Pages\ViewAntrianBarangMasuk::route('/{record}'),
            'edit' => Pages\LengkapiBarangMasuk::route('/{record}/edit'),
        ];
    }
}
