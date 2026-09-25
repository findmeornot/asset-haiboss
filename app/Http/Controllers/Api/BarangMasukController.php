<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetPhoto;
use App\Models\InventoryBalance;
use App\Models\InventoryBalanceUnit;
use App\Services\ActivityNotifier;
use App\Services\BarcodeNumberGenerator;
use App\Services\InventoryNumberGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Lapor Barang Datang tidak punya table sendiri — ini cuma tahap awal
 * lifecycle Asset. OB lapor foto_resi + keterangan + lokasi sementara,
 * record `Asset` langsung dibuat berikut kode barcode & inventory_number-nya,
 * admin melengkapi identitas barang lewat update(), lalu OB menyelesaikan
 * pengecekan fisik lewat complete().
 */
class BarangMasukController extends Controller
{
    /** Minimal foto fisik yang wajib diunggah sebelum pengecekan bisa diselesaikan. */
    private const MIN_PENGECEKAN_PHOTOS = 2;

    public function __construct(private ActivityNotifier $notifier) {}

    /**
     * URL publik sebuah file. Upload lama tersimpan di disk `public`, upload
     * baru di disk default (S3, sama dengan Filament). Cek disk lokal dulu
     * karena murah, baru fallback ke URL disk default.
     */
    private function fileUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        return Storage::disk(config('filesystems.default'))->url($path);
    }

    /**
     * Serialize satu laporan: `foto_resi` & foto fisik di kolom DB cuma path
     * relatif, jadi dikonversi ke URL publik penuh sebelum dikirim ke frontend.
     */
    private function serialize(Asset $asset): array
    {
        $data = $asset->toArray();
        $data['foto_resi'] = $this->fileUrl($asset->foto_resi);

        if ($asset->relationLoaded('photos')) {
            $data['photos'] = $asset->photos
                ->sortBy('sort_order')
                ->values()
                ->map(fn (AssetPhoto $photo) => [
                    'id' => $photo->id,
                    'photo_type' => $photo->photo_type,
                    'url' => $this->fileUrl($photo->file_path),
                    'original_filename' => $photo->original_filename,
                    'created_at' => $photo->created_at,
                ])
                ->all();
        }

        return $data;
    }

    /**
     * List laporan barang masuk dengan pagination, filter, dan search.
     *
     * Cuma nampilin Asset yang berasal dari alur lapor barang datang ini
     * (reported_by terisi), bukan seluruh katalog aset — lihat AssetController
     * untuk katalog aset penuh.
     *
     * Query params:
     * - search: cari di keterangan, name
     * - status, campus_id, location_id: filter relasi (ulid) / exact
     * - per_page: default 15, max 100
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        $query = Asset::query()
            ->whereNotNull('reported_by')
            ->with(['campus', 'location', 'reportedBy']);

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('keterangan', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('status')->trim()->value()) {
            $query->where('status', $status);
        } else {
            // Default: cuma barang yang masih di alur intake. Begitu pengecekan
            // selesai (status jadi `stock`), barang pindah ke katalog aset dan
            // tidak perlu muncul lagi di daftar Lapor Barang Datang.
            $query->whereIn('status', ['baru_dilaporkan', 'menunggu_pengecekan']);
        }

        if ($campusUlid = $request->string('campus_id')->trim()->value()) {
            $query->whereHas('campus', fn ($q) => $q->where('ulid', $campusUlid));
        }

        if ($locationUlid = $request->string('location_id')->trim()->value()) {
            $query->whereHas('location', fn ($q) => $q->where('ulid', $locationUlid));
        }

        $items = $query->latest()->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => collect($items->items())->map(fn (Asset $a) => $this->serialize($a))->all(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
            ],
        ]);
    }

    /**
     * Lapor Barang Datang: OB/user cuma isi foto resi, keterangan, & lokasi
     * sementara (opsional). Kode barcode & nomor aset langsung digenerate di
     * sini; identitas barang dilengkapi admin belakangan lewat update().
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'foto_resi' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'keterangan' => ['required', 'string'],
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Data tidak valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Disk default (S3) supaya foto resi ikut tampil di panel admin Filament,
        // yang memang membaca `foto_resi` dari disk tersebut.
        $disk = config('filesystems.default');
        $path = 'barang-masuk/' . Str::random(20) . '.' . $request->file('foto_resi')->extension();
        $request->file('foto_resi')->storeAs('barang-masuk', basename($path), $disk);

        try {
            $asset = Asset::create([
                'foto_resi' => $path,
                'keterangan' => trim($request->string('keterangan')->value()),
                'campus_id' => $request->input('campus_id'),
                'location_id' => $request->input('location_id'),
                'location_confirmed' => false,
                'reported_by' => $request->user()->id,
                'status' => 'baru_dilaporkan',
                // Kode barcode & nomor aset langsung terbit saat lapor, supaya
                // label bisa dicetak sebelum barang dicek OB.
                'barcode' => BarcodeNumberGenerator::generate(),
                'inventory_number' => InventoryNumberGenerator::generate(),
            ]);
        } catch (\InvalidArgumentException $e) {
            Storage::disk($disk)->delete($path);

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        $asset->load(['campus', 'location', 'reportedBy']);

        // Petugas inventaris perlu tahu ada laporan baru yang menunggu
        // kelengkapan data & penentuan lokasi.
        $this->notifier->barangDilaporkan($asset, $request->user());

        return response()->json([
            'message' => 'Laporan barang datang berhasil dikirim.',
            'data' => $this->serialize($asset),
        ], 201);
    }

    /**
     * Detail satu laporan, route key pakai ulid (lihat HasRouteUlid pada Asset).
     */
    public function show(Asset $barangMasuk): JsonResponse
    {
        $barangMasuk->load(['campus', 'location', 'reportedBy', 'pic', 'category', 'photos']);

        return response()->json([
            'data' => $this->serialize($barangMasuk),
        ]);
    }

    /**
     * Admin melengkapi identitas barang (name, category, inventory_number, dst)
     * & penentuan penempatan (gedung/ruangan), atau OB memperbarui penempatan
     * sementara. Endpoint ini sengaja hanya expose field-field seputar alur
     * barang masuk — field aset lain (financial, purchase, dst) tetap lewat
     * AssetController setelah barang resmi jadi aset aktif.
     */
    public function update(Request $request, Asset $barangMasuk): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'keterangan' => ['sometimes', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'inventory_number' => ['nullable', 'string', 'max:255', 'unique:assets,inventory_number,' . $barangMasuk->id],
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'location_confirmed' => ['sometimes', 'boolean'],
            'serial_number' => ['nullable', 'string', 'max:255', Rule::unique('assets', 'serial_number')->ignore($barangMasuk->id)->whereNull('deleted_at')],
            'status' => ['sometimes', 'string', 'in:baru_dilaporkan,menunggu_pengecekan,stock'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Data tidak valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $barangMasuk->update($validator->validated());
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        // Notifikasi "barang siap dicek" dikirim dari AssetObserver saat status
        // berubah jadi `menunggu_pengecekan`, supaya edit lewat panel admin
        // Filament juga ikut memberi tahu pelapor.
        $barangMasuk->load(['campus', 'location', 'reportedBy']);

        return response()->json([
            'message' => 'Laporan barang datang berhasil diperbarui.',
            'data' => $this->serialize($barangMasuk),
        ]);
    }

    /**
     * Cari pemakai kode barcode di seluruh data barang: aset (`assets.barcode`
     * & `assets.inventory_number`) maupun barang habis pakai
     * (`inventory_balances.master_barcode` & `inventory_balance_units.sub_barcode`).
     * Satu label barcode fisik cuma boleh nempel di satu barang.
     *
     * @return array{type: string, label: string, name: ?string, code: string}|null
     *         null kalau kode masih bebas dipakai.
     */
    private function findBarcodeOwner(string $code, ?int $ignoreAssetId = null): ?array
    {
        $code = trim($code);

        if ($code === '') {
            return null;
        }

        $asset = Asset::query()
            ->when($ignoreAssetId, fn ($q) => $q->whereKeyNot($ignoreAssetId))
            ->where(fn ($q) => $q->where('barcode', $code)->orWhere('inventory_number', $code))
            ->first();

        if ($asset) {
            return [
                'type' => 'asset',
                'label' => 'Barang/aset lain',
                'name' => $asset->name,
                'code' => $asset->barcode === $code ? $code : (string) $asset->inventory_number,
            ];
        }

        $balance = InventoryBalance::query()->where('master_barcode', $code)->first();

        if ($balance) {
            return [
                'type' => 'supply_master',
                'label' => 'Barang habis pakai (master)',
                'name' => $balance->name ?? null,
                'code' => $code,
            ];
        }

        $unit = InventoryBalanceUnit::query()->where('sub_barcode', $code)->first();

        if ($unit) {
            return [
                'type' => 'supply_unit',
                'label' => 'Unit barang habis pakai',
                'name' => $unit->inventoryBalance?->name,
                'code' => $code,
            ];
        }

        return null;
    }

    /**
     * Cek ketersediaan kode barcode sebelum OB menempelkan labelnya ke unit.
     * Dipakai frontend untuk validasi langsung saat kode dipindai/diketik.
     */
    public function checkBarcode(Request $request, Asset $barangMasuk): JsonResponse
    {
        $code = trim((string) $request->query('code', ''));

        if ($code === '') {
            return response()->json([
                'message' => 'Kode barcode wajib diisi.',
            ], 422);
        }

        $owner = $this->findBarcodeOwner($code, $barangMasuk->id);

        return response()->json([
            'data' => [
                'code' => $code,
                'available' => $owner === null,
                'used_by' => $owner,
            ],
        ]);
    }

    /**
     * Unggah satu foto fisik barang untuk proses Pengecekan (OB).
     * Satu `photo_type` = satu slot foto; unggah ulang slot yang sama akan
     * menimpa (foto lama dihapus berikut filenya).
     */
    public function storePhoto(Request $request, Asset $barangMasuk): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'photo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'photo_type' => ['required', 'string', Rule::in(AssetPhoto::TYPES)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Data tidak valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $photoType = $request->string('photo_type')->value();
        $file = $request->file('photo');

        // Disk default (S3), sama dengan foto barang yang diunggah admin lewat
        // Filament, supaya foto pengecekan langsung kelihatan di panel admin.
        $disk = config('filesystems.default');
        $path = $file->storeAs(
            'asset-photos',
            Str::random(20) . '.' . $file->extension(),
            $disk
        );

        // Timpa slot yang sama: hapus record lama (file ikut terhapus lewat
        // event `deleted` pada AssetPhoto).
        $barangMasuk->photos()->where('photo_type', $photoType)->get()
            ->each(fn (AssetPhoto $old) => $old->delete());

        $photo = $barangMasuk->photos()->create([
            'photo_type' => $photoType,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'sort_order' => array_search($photoType, AssetPhoto::TYPES, true) ?: 0,
        ]);

        return response()->json([
            'message' => 'Foto berhasil diunggah.',
            'data' => $this->serialize($barangMasuk->load(['campus', 'location', 'reportedBy', 'pic', 'category', 'photos'])),
            'photo' => [
                'id' => $photo->id,
                'photo_type' => $photo->photo_type,
                'url' => $this->fileUrl($photo->file_path),
            ],
        ], 201);
    }

    /**
     * Hapus satu foto fisik barang. Foto harus milik laporan yang bersangkutan.
     */
    public function destroyPhoto(Asset $barangMasuk, AssetPhoto $photo): JsonResponse
    {
        if ($photo->asset_id !== $barangMasuk->id) {
            return response()->json([
                'message' => 'Foto tidak ditemukan pada laporan ini.',
            ], 404);
        }

        $photo->delete();

        return response()->json([
            'message' => 'Foto berhasil dihapus.',
            'data' => $this->serialize($barangMasuk->load(['campus', 'location', 'reportedBy', 'pic', 'category', 'photos'])),
        ]);
    }

    /**
     * Selesaikan Pengecekan Barang (OB): konfirmasi foto fisik, label barcode
     * (hasil generate saat lapor) sudah ditempel, dan barang sudah berada di
     * lokasi yang ditentukan admin.
     *
     * Barang yang masih `baru_dilaporkan` belum boleh dicek — admin harus
     * melengkapi identitas & lokasi finalnya dulu (status jadi
     * `menunggu_pengecekan`).
     */
    public function complete(Request $request, Asset $barangMasuk): JsonResponse
    {
        if ($barangMasuk->status === 'baru_dilaporkan') {
            return response()->json([
                'message' => 'Barang belum bisa dicek. Admin belum melengkapi data & lokasi penempatannya.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'serial_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('assets', 'serial_number')->ignore($barangMasuk->id)->whereNull('deleted_at'),
            ],
            'kondisi' => ['sometimes', 'string', 'in:good,minor_damage,major_damage'],
            'keterangan' => ['sometimes', 'nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Data tidak valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($barangMasuk->photos()->count() < self::MIN_PENGECEKAN_PHOTOS) {
            return response()->json([
                'message' => 'Minimal ' . self::MIN_PENGECEKAN_PHOTOS . ' foto fisik barang wajib diunggah sebelum pengecekan diselesaikan.',
            ], 422);
        }

        $updates = [
            'kondisi' => $request->input('kondisi', 'good'),
            'location_confirmed' => true,
            'status' => 'stock',
        ];

        // Serial number unit sifatnya opsional — tidak semua barang punya, dan
        // yang didaftarkan di tahap ini memang kode barcode labelnya.
        if ($request->filled('serial_number')) {
            $updates['serial_number'] = trim($request->string('serial_number')->value());
        }

        if ($request->exists('keterangan')) {
            $updates['keterangan'] = $request->input('keterangan');
        }

        // Barcode & nomor aset sudah terbit saat lapor (store()). Fallback ini
        // cuma untuk laporan lama yang dibuat sebelum alur itu berlaku.
        if (blank($barangMasuk->barcode)) {
            $updates['barcode'] = BarcodeNumberGenerator::generate();
        }

        if (blank($barangMasuk->inventory_number)) {
            $updates['inventory_number'] = InventoryNumberGenerator::generate();
        }

        try {
            $barangMasuk->update($updates);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        $barangMasuk->load(['campus', 'location', 'reportedBy', 'pic', 'category', 'photos']);

        $this->notifier->pengecekanSelesai($barangMasuk, $request->user());

        return response()->json([
            'message' => 'Pengecekan barang selesai.',
            'data' => $this->serialize($barangMasuk),
        ]);
    }

    public function destroy(Asset $barangMasuk): JsonResponse
    {
        $barangMasuk->delete();

        return response()->json([
            'message' => 'Laporan barang datang berhasil dihapus.',
        ]);
    }
}
