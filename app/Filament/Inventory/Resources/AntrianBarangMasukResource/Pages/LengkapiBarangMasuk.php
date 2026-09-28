<?php

namespace App\Filament\Inventory\Resources\AntrianBarangMasukResource\Pages;

use App\Filament\Inventory\Resources\AntrianBarangMasukResource;
use App\Models\Asset;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Classification;
use App\Models\Location;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Services\BarcodeNumberGenerator;
use App\Services\InventoryNumberGenerator;
use Filament\Actions;
use Filament\Forms\Components;
use Filament\Resources\Pages\Concerns\HasWizard;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Wizard 3 step untuk Finance melengkapi laporan OB berdasarkan invoice
 * (baca: App\Http\Controllers\Api\BarangMasukController).
 *
 * Satu laporan OB = satu resi/box, isinya bisa beberapa jenis barang
 * berbeda dan masing-masing bisa lebih dari 1 unit. Karena itu:
 * - Step "Daftar Barang" berupa Repeater `items`, satu baris per jenis
 *   barang di invoice (jenis, nama, harga, jumlah, kategori sendiri).
 * - Step "Penempatan" berupa Repeater `placements` yang dibangun ulang dari
 *   `items` tiap kali Step 1 lolos validasi, supaya tiap jenis barang bisa
 *   ditaruh di Gedung/Ruangan berbeda. Relasi baris ke barang lewat
 *   `row_id`, karena key uuid Repeater hilang saat dehydrate (array_values).
 *
 * Saat submit: 1 Purchase, 1 PurchaseItem per baris, lalu Asset per unit.
 * Laporan OB sendiri jadi unit pertama barang pertama; unit lain dibuat
 * dengan `intake_parent_id` = laporan dan `reported_by` null, sehingga list
 * Barang Masuk tetap 1 row sementara antrean penempatan OB berisi semua unit.
 *
 * `jenis_barang` & `classification_id` murni transient state form, TIDAK ada
 * kolom database untuk ini.
 *
 * Otorisasi update (permission `assets.update`) sepenuhnya otomatis lewat
 * EditRecord::authorizeAccess() -> AssetPolicy::update() bawaan Filament,
 * tidak ada bypass di sini.
 */
class LengkapiBarangMasuk extends EditRecord
{
    use HasWizard;

    protected static string $resource = AntrianBarangMasukResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Lengkapi Data Barang Masuk';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => static::getResource()::getUrl('view', ['record' => $this->getRecord()])),
        ];
    }

    /**
     * Default EditRecord::getRedirectUrl() balik `null` (tetap di halaman
     * yang sama) kalau user authorized -- untuk Wizard ini efeknya state
     * step di Alpine (client) ke-reset ke Step 1 setelah render ulang,
     * kelihatan seperti "balik ke awal" padahal data sudah tersimpan.
     * Redirect eksplisit ke halaman View supaya Finance lihat hasil final.
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    /**
     * State form tidak berasal dari kolom Asset, jadi diisi manual: satu
     * baris barang kosong (nama awal dari laporan) & default pembelian.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [
            'items' => [
                (string) Str::uuid() => static::blankItem($data['name'] ?? null, $data['brand'] ?? null),
            ],
            'purchase_data' => [
                'purchase_date' => null,
                'ownership' => 'company',
            ],
            'placements' => [],
        ];
    }

    protected static function blankItem(?string $name = null, ?string $brand = null): array
    {
        return [
            'row_id' => (string) Str::uuid(),
            'jenis_barang' => null,
            'name' => $name,
            'brand' => $brand,
            'unit_price' => null,
            'quantity' => 1,
            'unit' => null,
            'total_price' => null,
            'classification_id' => null,
            'category_id' => null,
        ];
    }

    public function getSteps(): array
    {
        return [
            Step::make('Daftar Barang')
                ->description('Isi sesuai invoice, satu baris per jenis barang.')
                ->afterValidation(fn (callable $get, callable $set) => $this->syncPlacements($get, $set))
                ->schema([
                    Components\Repeater::make('items')
                        ->hiddenLabel()
                        ->addActionLabel('Tambah Barang')
                        ->minItems(1)
                        ->reorderable(false)
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => filled($state['name'] ?? null)
                            ? $state['name'].' ('.max(1, (int) ($state['quantity'] ?? 1)).' '.(($state['unit'] ?? null) ?: 'unit').')'
                            : 'Barang baru')
                        ->columns(3)
                        ->schema([
                            Components\Hidden::make('row_id')
                                ->default(fn () => (string) Str::uuid()),

                            Components\ToggleButtons::make('jenis_barang')
                                ->label('Jenis Barang')
                                ->options([
                                    'tidak_habis_pakai' => 'Tidak Habis Pakai',
                                    'habis_pakai' => 'Barang Habis Pakai',
                                ])
                                ->icons([
                                    'tidak_habis_pakai' => 'heroicon-o-archive-box',
                                    'habis_pakai' => 'heroicon-o-cube',
                                ])
                                ->colors([
                                    'tidak_habis_pakai' => 'primary',
                                    'habis_pakai' => 'warning',
                                ])
                                ->inline()
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn (callable $set, callable $get) => static::syncClassification($set, $get))
                                ->columnSpanFull(),

                            Components\TextInput::make('name')
                                ->label('Nama Barang')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true),

                            Components\Select::make('brand')
                                ->label('Merk/Tipe')
                                ->searchable()
                                ->options(function (callable $get) {
                                    $options = Asset::whereNotNull('brand')->distinct()->pluck('brand', 'brand')->toArray();
                                    $current = $get('brand');
                                    if ($current && ! isset($options[$current])) {
                                        $options[$current] = $current;
                                    }

                                    return $options;
                                })
                                ->createOptionForm([
                                    Components\TextInput::make('brand')->label('Merk/Tipe Baru')->required(),
                                ])
                                ->createOptionUsing(fn (array $data) => $data['brand']),

                            Components\Select::make('unit')
                                ->label('Satuan')
                                ->options([
                                    'Unit' => 'Unit', 'Pcs' => 'Pcs', 'Set' => 'Set',
                                    'Kg' => 'Kg', 'Paket' => 'Paket', 'Lembar' => 'Lembar',
                                    'Buah' => 'Buah', 'Meter' => 'Meter', 'Liter' => 'Liter',
                                ])
                                ->searchable()
                                ->native(false),

                            Components\TextInput::make('unit_price')
                                ->label('Harga Perolehan (per unit)')
                                ->numeric()
                                ->prefix('Rp')
                                ->minValue(0)
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (callable $set, callable $get) {
                                    static::syncTotalPrice($set, $get);
                                    static::syncClassification($set, $get);
                                }),

                            Components\TextInput::make('quantity')
                                ->label('Jumlah')
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->maxValue(500)
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (callable $set, callable $get) => static::syncTotalPrice($set, $get)),

                            Components\TextInput::make('total_price')
                                ->label('Total Harga')
                                ->numeric()
                                ->prefix('Rp')
                                ->disabled()
                                ->dehydrated(),

                            Components\Hidden::make('classification_id'),

                            Components\Placeholder::make('classification_display')
                                ->label('Klasifikasi')
                                ->content(fn (callable $get) => static::resolveClassification(
                                    $get('jenis_barang'),
                                    $get('unit_price'),
                                )?->name ?? 'Pilih Jenis Barang & isi Harga Perolehan terlebih dahulu.'),

                            Components\Select::make('category_id')
                                ->label('Kategori')
                                ->options(fn (callable $get) => $get('classification_id')
                                    ? Category::query()
                                        ->whereHas('classifications', fn ($q) => $q->whereKey($get('classification_id')))
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all()
                                    : [])
                                ->searchable()
                                ->required()
                                ->helperText('Kategori mengikuti Klasifikasi hasil Jenis Barang & Harga Perolehan.')
                                ->columnSpan(2),
                        ]),
                ]),

            Step::make('Data Pembelian')
                ->columns(2)
                ->schema([
                    Components\DatePicker::make('purchase_data.purchase_date')
                        ->label('Tahun Perolehan')
                        ->displayFormat('Y')
                        ->format('Y-m-d')
                        ->native(false),

                    Components\Select::make('purchase_data.ownership')
                        ->label('Sumber Dana')
                        ->options([
                            'company' => 'Yayasan',
                            'grant' => 'Hibah',
                            'loan' => 'Pinjaman',
                        ])
                        ->native(false)
                        ->required(),

                    Components\Placeholder::make('grand_total')
                        ->label('Total Invoice')
                        ->content(function (callable $get) {
                            $items = $get('items') ?? [];
                            $units = collect($items)->sum(fn ($item) => max(1, (int) ($item['quantity'] ?? 1)));
                            $total = collect($items)->sum(fn ($item) => static::lineTotal($item));

                            return count($items).' jenis barang, '.$units.' unit — Rp '
                                .number_format($total, 0, ',', '.');
                        })
                        ->columnSpanFull(),
                ]),

            Step::make('Penempatan')
                ->description('Barang dalam satu resi boleh ditempatkan di lokasi berbeda.')
                ->schema([
                    SchemaActions::make([
                        Actions\Action::make('samakanLokasi')
                            ->label('Samakan semua lokasi dengan barang pertama')
                            ->icon('heroicon-o-arrows-pointing-in')
                            ->link()
                            ->action(function (callable $get, callable $set) {
                                $placements = $get('placements') ?? [];
                                $first = reset($placements);

                                if (! $first) {
                                    return;
                                }

                                foreach ($placements as $key => $row) {
                                    $placements[$key]['campus_id'] = $first['campus_id'] ?? null;
                                    $placements[$key]['location_id'] = $first['location_id'] ?? null;
                                }

                                $set('placements', $placements);
                            }),
                    ]),

                    Components\Repeater::make('placements')
                        ->hiddenLabel()
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->defaultItems(0)
                        ->columns(3)
                        ->schema([
                            Components\Hidden::make('row_id'),
                            Components\Hidden::make('label'),

                            Components\Placeholder::make('barang')
                                ->label('Barang')
                                ->content(fn (callable $get) => $get('label') ?: '-'),

                            Components\Select::make('campus_id')
                                ->label('Gedung')
                                ->options(fn () => Campus::query()->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->live()
                                ->required()
                                ->afterStateUpdated(fn (callable $set) => $set('location_id', null)),

                            Components\Select::make('location_id')
                                ->label('Ruangan')
                                ->options(fn (callable $get) => $get('campus_id')
                                    ? Location::query()->where('campus_id', $get('campus_id'))->orderBy('name')->pluck('name', 'id')->all()
                                    : [])
                                ->searchable()
                                ->disabled(fn (callable $get) => blank($get('campus_id')))
                                ->placeholder(fn (callable $get) => blank($get('campus_id'))
                                    ? 'Pilih gedung terlebih dahulu'
                                    : 'Pilih ruangan (opsional)...'),
                        ]),
                ]),
        ];
    }

    /**
     * Bangun ulang baris penempatan dari daftar barang. Pilihan lokasi yang
     * sudah diisi dipertahankan (by row_id); barang baru default ke lokasi
     * yang dilaporkan OB.
     */
    protected function syncPlacements(callable $get, callable $set): void
    {
        $record = $this->getRecord();
        $items = $get('items') ?? [];
        $existing = collect($get('placements') ?? [])->keyBy('row_id');
        $placements = [];

        foreach ($items as $key => $item) {
            if (blank($item['row_id'] ?? null)) {
                $item['row_id'] = (string) Str::uuid();
                $set("items.{$key}.row_id", $item['row_id']);
            }

            $current = $existing->get($item['row_id'], []);

            $placements[(string) Str::uuid()] = [
                'row_id' => $item['row_id'],
                'label' => ($item['name'] ?? 'Barang').' — '.max(1, (int) ($item['quantity'] ?? 1)).' '.(($item['unit'] ?? null) ?: 'unit'),
                'campus_id' => $current['campus_id'] ?? $record->campus_id,
                'location_id' => array_key_exists('location_id', $current) ? $current['location_id'] : $record->location_id,
            ];
        }

        $set('placements', $placements);
    }

    /**
     * Satu-satunya tempat aturan "Jenis Barang + Harga -> Classification"
     * ditentukan. Dipakai baik untuk reaktivitas form maupun saat submit,
     * supaya tidak ada logic yang menduplikasi aturan ini.
     */
    protected static function resolveClassification(?string $jenisBarang, mixed $unitPrice): ?Classification
    {
        if ($jenisBarang === 'habis_pakai') {
            return Classification::where('slug', 'barang-habis-pakai')->first();
        }

        if ($jenisBarang === 'tidak_habis_pakai') {
            $price = is_numeric($unitPrice) ? (float) $unitPrice : 0.0;
            $slug = $price >= PurchaseItem::CAPITALIZATION_THRESHOLD ? 'aset' : 'inventaris';

            return Classification::where('slug', $slug)->first();
        }

        return null;
    }

    protected static function syncClassification(callable $set, callable $get): void
    {
        $classification = static::resolveClassification($get('jenis_barang'), $get('unit_price'));
        $newId = $classification?->id;

        if ($get('classification_id') !== $newId) {
            $set('category_id', null);
        }

        $set('classification_id', $newId);
    }

    protected static function syncTotalPrice(callable $set, callable $get): void
    {
        $price = $get('unit_price');

        $set('total_price', is_numeric($price)
            ? (float) $price * max(1, (int) ($get('quantity') ?: 1))
            : null);
    }

    protected static function lineTotal(array $item): float
    {
        $price = is_numeric($item['unit_price'] ?? null) ? (float) $item['unit_price'] : 0.0;

        return $price * max(1, (int) ($item['quantity'] ?? 1));
    }

    /**
     * Orchestration submit: 1 Purchase untuk resi ini, 1 PurchaseItem per
     * jenis barang, lalu Asset per unit fisik. Tidak reuse
     * CreateAsset::handleRecordCreation() karena method itu selalu membuat
     * Asset baru dan me-reroute barang habis pakai ke InventoryBalance —
     * di alur intake, semua barang (termasuk habis pakai) wajib melewati
     * antrean penempatan OB sebagai unit Asset.
     *
     * Status semua unit `menunggu_pengecekan` supaya dilanjutkan OB untuk
     * pengecekan fisik via API.
     *
     * Guard anti-duplicate: authorizeAccess() (lewat
     * AntrianBarangMasukResource::getEditAuthorizationResponse()) cuma
     * dicek sekali di mount() Livewire, bukan tiap submit — jadi kalau
     * record ini sempat dilengkapi dari tab/sesi lain SETELAH wizard ini
     * ke-mount tapi SEBELUM disubmit, mount()-nya sudah lolos duluan.
     * Re-check purchase_item_id di sini SAJA masih race: dua submit
     * konkuren bisa sama-sama baca null sebelum salah satunya commit.
     * Makanya re-check dilakukan di dalam transaction dengan
     * lockForUpdate() supaya submit kedua benar-benar menunggu submit
     * pertama commit, baru baca purchase_item_id yang sudah terisi.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $items = array_values($data['items'] ?? []);

        if ($items === []) {
            throw ValidationException::withMessages([
                'data.items' => 'Minimal satu barang harus diisi.',
            ]);
        }

        $placements = collect($data['placements'] ?? [])->keyBy('row_id');
        $purchaseData = $data['purchase_data'] ?? [];

        try {
            return DB::transaction(function () use ($record, $items, $placements, $purchaseData) {
                $locked = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
                $this->ensureNotAlreadyProcessed($locked);

                $purchase = Purchase::create([
                    'purchase_date' => $purchaseData['purchase_date'] ?? null,
                    'ownership' => $purchaseData['ownership'] ?? 'company',
                    'total_amount' => collect($items)->sum(fn ($item) => static::lineTotal($item)),
                ]);

                $lines = collect($items)->map(function (array $item) use ($purchase, $placements, $record) {
                    $classification = static::resolveClassification($item['jenis_barang'] ?? null, $item['unit_price'] ?? null);
                    $placement = $placements->get($item['row_id'] ?? null, []);

                    return [
                        'item' => $item,
                        'classification' => $classification,
                        'purchase_item' => $this->createPurchaseItem($purchase, $item, $classification),
                        'campus_id' => $placement['campus_id'] ?? $record->campus_id,
                        'location_id' => array_key_exists('location_id', $placement) ? $placement['location_id'] : $record->location_id,
                    ];
                });

                $this->updateMasterAsset($record, $lines->first());
                $this->createIntakeUnits($record, $lines);

                return $record->refresh();
            });
        } catch (\InvalidArgumentException $e) {
            // Guard Asset::saving (kategori vs klasifikasi, ruangan vs gedung).
            throw ValidationException::withMessages([
                'data.items' => $e->getMessage(),
            ]);
        }
    }

    private function ensureNotAlreadyProcessed(Model $record): void
    {
        if (filled($record->purchase_item_id)) {
            throw ValidationException::withMessages([
                'data.items' => 'Barang ini sudah dilengkapi Finance dan tidak dapat diproses ulang.',
            ]);
        }
    }

    private function createPurchaseItem(Purchase $purchase, array $item, ?Classification $classification): PurchaseItem
    {
        $unitPrice = is_numeric($item['unit_price'] ?? null) ? (float) $item['unit_price'] : 0.0;

        return PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'category_id' => $item['category_id'] ?? null,
            'classification_id' => $classification?->id,
            'name' => $item['name'] ?? null,
            'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
            'unit' => $item['unit'] ?? null,
            'unit_price' => $unitPrice,
            'total_price' => static::lineTotal($item),
            'is_capitalized' => PurchaseItem::isCapitalizable($unitPrice, $classification),
        ]);
    }

    private function assetAttributes(array $line): array
    {
        return [
            'classification_id' => $line['classification']?->id,
            'category_id' => $line['item']['category_id'] ?? null,
            'name' => $line['item']['name'] ?? null,
            'brand' => $line['item']['brand'] ?? null,
            'campus_id' => $line['campus_id'],
            'location_id' => $line['location_id'],
            'purchase_item_id' => $line['purchase_item']->id,
            'status' => 'menunggu_pengecekan',
        ];
    }

    /**
     * Laporan OB jadi unit pertama dari barang pertama; barcode & nomor
     * inventaris yang terbit saat lapor tetap dipakai.
     */
    private function updateMasterAsset(Model $record, array $line): void
    {
        $updates = $this->assetAttributes($line);

        if (blank($record->inventory_number)) {
            $updates['inventory_number'] = InventoryNumberGenerator::generate();
        }

        $record->update($updates);
    }

    /**
     * Unit sisanya: barang pertama qty-1, barang lain sebanyak qty-nya.
     * `reported_by` sengaja null — unit ini berasal dari invoice Finance,
     * bukan laporan OB — dan relasi ke laporan lewat `intake_parent_id`.
     */
    private function createIntakeUnits(Model $record, $lines): void
    {
        $counts = $lines->values()->map(fn (array $line, int $index) => $line['purchase_item']->quantity - ($index === 0 ? 1 : 0));
        $total = $counts->sum();

        if ($total < 1) {
            return;
        }

        $barcodes = BarcodeNumberGenerator::generateBulk($total);
        $inventoryNumbers = InventoryNumberGenerator::generateBulk($total);

        foreach ($lines->values() as $index => $line) {
            for ($i = 0; $i < $counts[$index]; $i++) {
                $unit = $record->replicate();
                $unit->fill($this->assetAttributes($line));
                $unit->barcode = array_shift($barcodes);
                $unit->inventory_number = array_shift($inventoryNumbers);
                $unit->foto_resi = null;
                $unit->reported_by = null;
                $unit->intake_parent_id = $record->getKey();
                $unit->save();
            }
        }
    }
}
