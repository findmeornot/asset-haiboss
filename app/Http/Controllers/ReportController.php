<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\StockOpname;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function exportExcel(Request $request)
    {
        abort_unless(Auth::user()->hasPermissionTo('reports.view'), 403);

        $type = $request->input('report_type');
        $campusId = $request->input('campus_id');
        $locationId = $request->input('location_id');
        $categoryId = $request->input('category_id');
        $ownership = $request->input('ownership');
        $year = $request->input('year');

        // Check if user can view financial
        $canViewFinancial = Auth::user()->hasPermissionTo('financial.view');

        // Determine data source based on report type
        if ($type === 'movements') {
            $query = AssetMovement::with(['asset', 'sourceCampus', 'destinationCampus', 'requestedBy', 'approvedBy']);
        } elseif ($type === 'stock_opname') {
            $query = StockOpname::with(['campus', 'location', 'items']);
        } else {
            $query = Asset::with(['category', 'campus', 'location', 'pic', 'purchase']);
            
            // Apply specific filters based on report type
            if ($type === 'damaged') {
                $query->whereIn('status', ['minor_damage', 'major_damage']);
            } elseif ($type === 'lost') {
                $query->where('status', 'lost');
            } elseif ($type === 'borrowed') {
                $query->where('status', 'borrowed');
            }
        }

        // Apply general filters for Assets
        if (!in_array($type, ['movements', 'stock_opname'])) {
            if ($campusId) $query->where('campus_id', $campusId);
            if ($locationId) $query->where('location_id', $locationId);
            if ($categoryId) $query->where('category_id', $categoryId);
            if ($ownership) $query->where('ownership', $ownership);
            if ($year) {
                $query->whereHas('purchase', function($q) use ($year) {
                    $q->whereYear('purchase_date', $year);
                });
            }
        }

        $data = $query->get();
        $filename = "Laporan_Aset_" . date('Y-m-d_His') . ".xls";

        $html = view('documents.excel-report', [
            'type' => $type,
            'data' => $data,
            'canViewFinancial' => $canViewFinancial,
            'printedBy' => Auth::user()->name,
            'printedAt' => now(),
        ])->render();

        return response()->streamDownload(function () use ($html) {
            echo $html;
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
