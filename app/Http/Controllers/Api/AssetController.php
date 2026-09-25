<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    /**
     * List barang (assets) dengan pagination, filter, dan search.
     *
     * Query params:
     * - search: cari di name, inventory_number, serial_number, barcode
     * - category_id, classification_id, campus_id, location_id, pic_id: filter relasi (ulid)
     * - mine: jika truthy, override pic_id dengan employee milik user yang sedang login
     *   (dipakai halaman "Barang Saya"; user tanpa data employee akan dapat list kosong)
     * - status, ownership: filter exact
     * - per_page: default 15, max 100
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        $query = Asset::query()
            ->with(['category', 'classification', 'campus', 'location', 'pic', 'photos']);

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('inventory_number', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($categoryUlid = $request->string('category_id')->trim()->value()) {
            $query->whereHas('category', fn ($q) => $q->where('ulid', $categoryUlid));
        }

        if ($classificationUlid = $request->string('classification_id')->trim()->value()) {
            $query->whereHas('classification', fn ($q) => $q->where('ulid', $classificationUlid));
        }

        if ($campusUlid = $request->string('campus_id')->trim()->value()) {
            $query->whereHas('campus', fn ($q) => $q->where('ulid', $campusUlid));
        }

        if ($locationUlid = $request->string('location_id')->trim()->value()) {
            $query->whereHas('location', fn ($q) => $q->where('ulid', $locationUlid));
        }

        if ($request->boolean('mine')) {
            $employee = $request->user()->employee;
            $query->where('pic_id', $employee?->id ?? 0);
        } elseif ($picUlid = $request->string('pic_id')->trim()->value()) {
            $query->whereHas('pic', fn ($q) => $q->where('ulid', $picUlid));
        }

        if ($status = $request->string('status')->trim()->value()) {
            $query->where('status', $status);
        } else {
            // Default katalog: sembunyikan barang yang baru dilaporkan & belum
            // melalui proses input invoice/unboxing/penempatan.
            $query->where('status', '!=', 'baru_dilaporkan');
        }

        if ($ownership = $request->string('ownership')->trim()->value()) {
            $query->where('ownership', $ownership);
        }

        $assets = $query->latest()->paginate($perPage)->withQueryString();

        // Thumbnail list: foto fisik utama barang (URL publik), bukan relasi photos
        // mentah — cukup satu URL per baris.
        $items = collect($assets->items())->map(function (Asset $asset) {
            $data = $asset->toArray();
            unset($data['photos']);
            $data['thumbnail_url'] = $this->primaryPhoto($asset)?->url;

            return $data;
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $assets->currentPage(),
                'per_page' => $assets->perPage(),
                'total' => $assets->total(),
                'last_page' => $assets->lastPage(),
                'from' => $assets->firstItem(),
                'to' => $assets->lastItem(),
            ],
        ]);
    }

    /**
     * Foto utama barang: tampak depan, kalau tidak ada foto pertama menurut sort_order.
     */
    private function primaryPhoto(Asset $asset): ?AssetPhoto
    {
        $photos = $asset->photos->sortBy('sort_order')->values();

        return $photos->firstWhere('photo_type', AssetPhoto::TYPE_TAMPAK_DEPAN) ?? $photos->first();
    }

    /**
     * Detail satu barang (asset), route key pakai ulid (lihat HasRouteUlid).
     */
    public function show(Asset $asset): JsonResponse
    {
        $asset->load(['category', 'classification', 'campus', 'location', 'pic', 'purchase', 'financial', 'photos']);

        // Foto fisik barang (hasil pengecekan OB): kolom DB cuma path relatif,
        // jadi kirim URL publik penuh supaya bisa langsung ditampilkan frontend.
        $data = $asset->toArray();
        $data['photos'] = $asset->photos
            ->sortBy('sort_order')
            ->values()
            ->map(fn (AssetPhoto $photo) => [
                'id' => $photo->id,
                'photo_type' => $photo->photo_type,
                'url' => $photo->url,
            ])
            ->all();

        return response()->json([
            'data' => $data,
        ]);
    }
}
