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
use Illuminate\Support\HtmlString;

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
        return 'Barang Masuk';
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
        return 'Barang Masuk';
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

                        Components\Placeholder::make('finance_invoice_items')
                            ->label('Rincian Invoice')
                            ->visible(fn (?Asset $record) => filled($record?->purchase_item_id))
                            ->content(fn (?Asset $record) => static::renderInvoiceItems($record))
                            ->columnSpanFull(),

                        Section::make('Pembelian')
                            ->visible(fn (?Asset $record) => filled($record?->purchase_item_id))
                            ->schema([
                                Components\Placeholder::make('finance_total_amount')
                                    ->label('Total Invoice')
                                    ->content(fn (?Asset $record) => $record?->purchaseItem?->purchase?->total_amount !== null
                                        ? 'Rp '.number_format((float) $record->purchaseItem->purchase->total_amount, 0, ',', '.')
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
                    ]),
            ])
            ->columns(1);
    }

    /**
     * Semua unit hasil satu laporan (laporan itu sendiri + intakeUnits),
     * dikelompokkan per PurchaseItem: satu baris per jenis barang di invoice.
     */
    protected static function renderInvoiceItems(?Asset $record): HtmlString
    {
        if (! $record) {
            return new HtmlString('-');
        }

        $units = Asset::query()
            ->where(fn ($q) => $q->whereKey($record->getKey())->orWhere('intake_parent_id', $record->getKey()))
            ->with(['purchaseItem', 'classification', 'category', 'campus', 'location'])
            ->orderBy('id')
            ->get()
            ->groupBy('purchase_item_id');

        $rupiah = fn ($value) => $value !== null ? 'Rp '.number_format((float) $value, 0, ',', '.') : '-';

        $rows = $units->map(function ($group) use ($rupiah) {
            $first = $group->first();
            $item = $first->purchaseItem;
            $lokasi = $group
                ->map(fn (Asset $unit) => trim(($unit->campus?->name ?? '-').($unit->location ? ' / '.$unit->location->name : '')))
                ->unique()
                ->implode(', ');

            return '<tr class="border-t border-gray-200 dark:border-white/10">'
                .'<td class="py-2 pe-3">'.e($first->name ?: '-').($first->brand ? '<div class="text-xs text-gray-500">'.e($first->brand).'</div>' : '').'</td>'
                .'<td class="py-2 pe-3">'.e($first->classification?->name ?? '-').'<div class="text-xs text-gray-500">'.e($first->category?->name ?? '-').'</div></td>'
                .'<td class="py-2 pe-3 text-right">'.$group->count().' '.e($item?->unit ?: 'unit').'</td>'
                .'<td class="py-2 pe-3 text-right">'.$rupiah($item?->unit_price).'</td>'
                .'<td class="py-2 pe-3 text-right">'.$rupiah($item?->total_price).'</td>'
                .'<td class="py-2">'.e($lokasi).'</td>'
                .'</tr>';
        })->implode('');

        return new HtmlString(
            '<div class="overflow-x-auto"><table class="w-full text-sm">'
            .'<thead><tr class="text-left text-gray-500">'
            .'<th class="pb-2 pe-3 font-medium">Barang</th>'
            .'<th class="pb-2 pe-3 font-medium">Klasifikasi / Kategori</th>'
            .'<th class="pb-2 pe-3 font-medium text-right">Jumlah</th>'
            .'<th class="pb-2 pe-3 font-medium text-right">Harga Satuan</th>'
            .'<th class="pb-2 pe-3 font-medium text-right">Total</th>'
            .'<th class="pb-2 font-medium">Penempatan</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table></div>'
        );
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
                    ->height(64)
                    ->extraImgAttributes(['class' => 'object-cover rounded-lg'])
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

                Tables\Columns\TextColumn::make('intake_units_count')
                    ->label('Jumlah Unit')
                    ->counts('intakeUnits')
                    ->formatStateUsing(fn ($state, Asset $record) => $record->purchase_item_id ? ((int) $state + 1).' unit' : '-')
                    ->alignCenter(),

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
                    ->label('Invoice')
                    ->icon('heroicon-o-plus'),
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
