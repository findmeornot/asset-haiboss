<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Asset;
use App\Models\Location;
use Illuminate\Support\Facades\Auth;

class BulkPrintController extends Controller
{
    public function print(Request $request)
    {
        abort_unless(Auth::check(), 403);

        $locationId = $request->input('location_id');
        
        if (!$locationId) {
            return redirect()->back()->with('error', 'Ruangan tidak dipilih.');
        }

        $location = Location::with('campus')->findOrFail($locationId);

        // Fetch assets with eager loading, sorting Name ASC -> SKU ASC
        $assets = Asset::with(['category', 'classification', 'location', 'campus', 'pic'])
            ->where('location_id', $locationId)
            ->whereNotNull('barcode')
            ->orderBy('name', 'asc')
            ->orderBy('inventory_number', 'asc')
            ->get();

        if ($assets->isEmpty()) {
            return "Tidak ada barang pada ruangan ini yang memiliki barcode.";
        }

        return view('asset-bulk-print', compact('assets', 'location'));
    }
}
