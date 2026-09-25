<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * List ruangan (location) dengan pagination, filter campus_id (Gedung), dan search (name).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        // assets_count sama filter default katalog aset (sembunyikan baru_dilaporkan).
        $query = Location::query()
            ->with('campus')
            ->withCount(['assets' => fn ($q) => $q->where('status', '!=', 'baru_dilaporkan')]);

        if ($search = $request->string('search')->trim()->value()) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($campusUlid = $request->string('campus_id')->trim()->value()) {
            $query->whereHas('campus', fn ($q) => $q->where('ulid', $campusUlid));
        }

        $locations = $query->orderBy('name')->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => $locations->items(),
            'meta' => [
                'current_page' => $locations->currentPage(),
                'per_page' => $locations->perPage(),
                'total' => $locations->total(),
                'last_page' => $locations->lastPage(),
                'from' => $locations->firstItem(),
                'to' => $locations->lastItem(),
            ],
        ]);
    }
}
