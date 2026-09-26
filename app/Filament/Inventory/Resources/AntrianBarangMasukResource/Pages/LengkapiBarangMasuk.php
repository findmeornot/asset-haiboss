<?php

namespace App\Filament\Inventory\Resources\AntrianBarangMasukResource\Pages;

use App\Filament\Inventory\Resources\AntrianBarangMasukResource;
use App\Models\Asset;
use App\Models\Classification;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Services\BarcodeNumberGenerator;
use App\Services\InventoryNumberGenerator;
use Filament\Actions;
use Filament\Forms\Components;
use Filament\Resources\Pages\Concerns\HasWizard;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Wizard 3 step untuk Finance melengkapi Asset hasil laporan OB
 * (baca: App\Http\Controllers\Api\BarangMasukController).
 *
 * Field schema Identitas/Pembelian/Penempatan diadaptasi dari
 * App\Filament\Inventory\Resources\AssetResource::form() — bukan reuse
 * langsung, karena beberapa disabled()/visible() di sana khusus untuk alur
 * create-Asset-baru dan tidak berlaku untuk melengkapi Asset existing.
 *
 * `jenis_barang` & `classification_id` (Hidden) murni transient state form,
 * TIDAK ada kolom database untuk ini. `purchase_data.*` mengikuti pola
 * statePath yang sama dengan section "Pembelian" di AssetResource.
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

    public function getSteps(): array
    {
        return [
            Step::make('Identitas Barang')
                ->schema([
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
                        ->afterStateUpdated(fn (callable $set, callable $get) => static::syncClassification($set, $get)),

                    Components\TextInput::make('name')
                        ->label('Nama Barang')
                        ->required()
                        ->maxLength(255),

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

                    Components\Hidden::make('classification_id'),
                ]),

            Step::make('Data Pembelian')
                ->columns(2)
                ->schema([
                    Components\TextInput::make('purchase_data.unit_price')
                        ->label('Harga Perolehan')
                        ->numeric()
                        ->prefix('Rp')
                        ->minValue(0)
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (callable $set, callable $get) {
                            static::syncTotalPrice($set, $get);
                            static::syncClassification($set, $get);
                        }),

                    Components\TextInput::make('purchase_data.quantity')
                        ->label('Jumlah')
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (callable $set, callable $get) => static::syncTotalPrice($set, $get)),

                    Components\Select::make('purchase_data.unit')
                        ->label('Satuan')
                        ->options([
                            'Unit' => 'Unit', 'Pcs' => 'Pcs', 'Set' => 'Set',
                            'Kg' => 'Kg', 'Paket' => 'Paket', 'Lembar' => 'Lembar',
                            'Buah' => 'Buah', 'Meter' => 'Meter', 'Liter' => 'Liter',
                        ])
                        ->searchable()
                        ->native(false),

                    Components\TextInput::make('purchase_data.total_price')
                        ->label('Total Harga')
                        ->numeric()
                        ->prefix('Rp')
                        ->disabled()
                        ->dehydrated(),

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
                        ->required()
                        ->default('company'),

                    Components\Placeholder::make('classification_display')
                        ->label('Klasifikasi')
                        ->content(function (callable $get) {
                            $classification = static::resolveClassification(
                                $get('jenis_barang'),
                                $get('purchase_data.unit_price'),
                            );

                            return $classification?->name
                                ?? 'Pilih Jenis Barang & isi Harga Perolehan terlebih dahulu.';
                        })
                        ->columnSpanFull(),

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
                        ->required()
                        ->helperText('Kategori mengikuti Klasifikasi hasil Jenis Barang & Harga Perolehan di atas.')
                        ->columnSpanFull(),
                ]),

            Step::make('Penempatan')
                ->columns(2)
                ->schema([
                    Components\Select::make('campus_id')
                        ->label('Gedung')
                        ->relationship('campus', 'name')
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required()
                        ->afterStateUpdated(fn (callable $set) => $set('location_id', null)),

                    Components\Select::make('location_id')
                        ->label('Ruangan')
                        ->relationship(
                            name: 'location',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query, callable $get) => $get('campus_id')
                                ? $query->where('campus_id', $get('campus_id'))
                                : $query->whereRaw('1 = 0'),
                        )
                        ->searchable()
                        ->preload()
                        ->disabled(fn (callable $get) => blank($get('campus_id')))
                        ->placeholder(fn (callable $get) => blank($get('campus_id'))
                            ? 'Pilih gedung terlebih dahulu'
                            : 'Pilih ruangan (opsional)...')
                        ->helperText('Opsional.'),
                ]),
        ];
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
        $classification = static::resolveClassification($get('jenis_barang'), $get('purchase_data.unit_price'));
        $newId = $classification?->id;

        if ($get('classification_id') !== $newId) {
            $set('category_id', null);
        }

        $set('classification_id', $newId);
    }

    protected static function syncTotalPrice(callable $set, callable $get): void
    {
        $quantity = (int) ($get('purchase_data.quantity') ?: 1);
        $price = $get('purchase_data.unit_price');
        $price = is_numeric($price) ? (float) $price : null;

        $set('purchase_data.total_price', $price !== null ? $price * $quantity : null);
    }

    /**
     * Orchestration submit: buat Purchase + PurchaseItem baru, attach ke
     * Asset existing, lalu update field identitas/penempatan Asset. Tidak
     * reuse EditAsset::afterSave() karena fallback-nya menulis ke legacy
     * AssetPurchase. Tidak reuse CreateAsset::handleRecordCreation() apa
     * adanya karena method itu selalu membuat Asset baru (dan untuk
     * classification barang-habis-pakai bahkan reroute ke InventoryBalance).
     *
     * Status Asset diubah ke `menunggu_pengecekan` supaya bisa dilanjutkan
     * OB untuk pengecekan fisik via API.
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
        $purchaseData = $data['purchase_data'] ?? [];
        $classification = static::resolveClassification(
            $data['jenis_barang'] ?? null,
            $purchaseData['unit_price'] ?? null,
        );

        return DB::transaction(function () use ($record, $data, $purchaseData, $classification) {
            $locked = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $this->ensureNotAlreadyProcessed($locked);

            $purchaseItem = $this->createPurchaseTransaction($data, $purchaseData, $classification);

            $this->updateMasterAsset($record, $data, $purchaseItem, $classification);

            if ($purchaseItem->quantity > 1) {
                $this->fanOutAssetQuantity($record, $purchaseItem->quantity);
            }

            return $record->refresh();
        });
    }

    private function ensureNotAlreadyProcessed(Model $record): void
    {
        if (filled($record->purchase_item_id)) {
            throw ValidationException::withMessages([
                'data.jenis_barang' => 'Barang ini sudah dilengkapi Finance dan tidak dapat diproses ulang.',
            ]);
        }
    }

    private function createPurchaseTransaction(array $data, array $purchaseData, ?Classification $classification): PurchaseItem
    {
        $quantity = max(1, (int) ($purchaseData['quantity'] ?? 1));
        $unitPrice = isset($purchaseData['unit_price']) && $purchaseData['unit_price'] !== ''
            ? (float) $purchaseData['unit_price']
            : 0.0;
        $totalPrice = $unitPrice * $quantity;

        $purchase = Purchase::create([
            'purchase_date' => $purchaseData['purchase_date'] ?? null,
            'ownership' => $purchaseData['ownership'] ?? 'company',
            'total_amount' => $totalPrice,
        ]);

        return PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'category_id' => $data['category_id'] ?? null,
            'classification_id' => $classification?->id,
            'name' => $data['name'] ?? null,
            'quantity' => $quantity,
            'unit' => $purchaseData['unit'] ?? null,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'is_capitalized' => PurchaseItem::isCapitalizable($unitPrice, $classification),
        ]);
    }

    private function updateMasterAsset(Model $record, array $data, PurchaseItem $purchaseItem, ?Classification $classification): void
    {
        $updates = [
            'classification_id' => $classification?->id,
            'category_id' => $data['category_id'] ?? null,
            'name' => $data['name'] ?? $record->name,
            'brand' => $data['brand'] ?? null,
            'campus_id' => $data['campus_id'] ?? null,
            'location_id' => $data['location_id'] ?? null,
            'purchase_item_id' => $purchaseItem->id,
            'status' => 'menunggu_pengecekan',
        ];

        if (blank($record->inventory_number)) {
            $updates['inventory_number'] = InventoryNumberGenerator::generate();
        }

        $record->update($updates);
    }

    private function fanOutAssetQuantity(Model $record, int $quantity): void
    {
        // Fan-out: satu laporan OB bisa berisi N unit fisik. Unit pertama
        // adalah record OB asli (sudah di-update di atas). Unit ke-2..N
        // di-clone dari record asli dengan barcode + inventory_number baru.
        for ($i = 1; $i < $quantity; $i++) {
            $clone = $record->replicate();
            $clone->barcode = BarcodeNumberGenerator::generate();
            $clone->inventory_number = InventoryNumberGenerator::generate();
            $clone->foto_resi = null;
            $clone->reported_by = $record->reported_by;
            $clone->save();
        }
    }
}
