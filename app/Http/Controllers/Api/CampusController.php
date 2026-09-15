<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampusController extends Controller
{
    /**
     * List gedung (campus) dengan pagination dan search (name).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        $query = Campus::query();

        if ($search = $request->string('search')->trim()->value()) {
            $query->where('name', 'like', "%{$search}%");
        }

        $campuses = $query->orderBy('name')->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => $campuses->items(),
            'meta' => [
                'current_page' => $campuses->currentPage(),
                'per_page' => $campuses->perPage(),
                'total' => $campuses->total(),
                'last_page' => $campuses->lastPage(),
                'from' => $campuses->firstItem(),
                'to' => $campuses->lastItem(),
            ],
        ]);
    }
}
