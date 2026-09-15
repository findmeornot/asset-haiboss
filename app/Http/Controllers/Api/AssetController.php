<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
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
            ->with(['category', 'classification', 'campus', 'location', 'pic']);

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

        return response()->json([
            'data' => $assets->items(),
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
     * Detail satu barang (asset), route key pakai ulid (lihat HasRouteUlid).
     */
    public function show(Asset $asset): JsonResponse
    {
        $asset->load(['category', 'classification', 'campus', 'location', 'pic', 'purchase', 'financial']);

        return response()->json([
            'data' => $asset,
        ]);
    }
}
