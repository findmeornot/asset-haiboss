<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Lapor Barang Datang tidak punya table sendiri — ini cuma tahap awal
 * lifecycle Asset. OB lapor foto_resi + keterangan + lokasi sementara,
 * record `Asset` langsung dibuat (identitas barang & inventory_number masih
 * kosong), admin melengkapi sisanya lewat update() sampai nomor aset
 * ditentukan saat proses Penempatan.
 */
class BarangMasukController extends Controller
{
    /**
     * Serialize satu laporan: `foto_resi` di kolom DB cuma path relatif,
     * jadi dikonversi ke URL publik penuh sebelum dikirim ke frontend.
     */
    private function serialize(Asset $asset): array
    {
        $data = $asset->toArray();
        $data['foto_resi'] = $asset->foto_resi
            ? Storage::disk('public')->url($asset->foto_resi)
            : null;

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
     * sementara (opsional). Identitas barang & nomor aset dilengkapi admin
     * belakangan lewat update() — nomor baru dipasang saat proses Penempatan.
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

        $path = 'barang-masuk/' . Str::random(20) . '.' . $request->file('foto_resi')->extension();
        Storage::disk('public')->makeDirectory('barang-masuk');
        $request->file('foto_resi')->storeAs('barang-masuk', basename($path), 'public');

        try {
            $asset = Asset::create([
                'foto_resi' => $path,
                'keterangan' => trim($request->string('keterangan')->value()),
                'campus_id' => $request->input('campus_id'),
                'location_id' => $request->input('location_id'),
                'location_confirmed' => false,
                'reported_by' => $request->user()->id,
                'status' => 'baru_dilaporkan',
            ]);
        } catch (\InvalidArgumentException $e) {
            Storage::disk('public')->delete($path);

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Laporan barang datang berhasil dikirim.',
            'data' => $this->serialize($asset->load(['campus', 'location', 'reportedBy'])),
        ], 201);
    }

    /**
     * Detail satu laporan, route key pakai ulid (lihat HasRouteUlid pada Asset).
     */
    public function show(Asset $barangMasuk): JsonResponse
    {
        $barangMasuk->load(['campus', 'location', 'reportedBy']);

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
            'status' => ['sometimes', 'string', 'in:baru_dilaporkan,menunggu_invoice,siap_unboxing,selesai'],
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

        return response()->json([
            'message' => 'Laporan barang datang berhasil diperbarui.',
            'data' => $this->serialize($barangMasuk->load(['campus', 'location', 'reportedBy'])),
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
