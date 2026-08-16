<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssetImportTemplateController extends Controller
{
    /**
     * Generate dan download template import Asset dalam format CSV.
     * File ini memiliki header yang benar + 1 baris contoh data.
     */
    public function downloadCsv(Request $request)
    {
        abort_unless(Auth::check(), 403);

        $headers = [
            'No',
            'Kode',
            'Kategori Akuntansi',
            'Kategori',
            'Nama Barang',
            'Merk/Tipe',
            'Nomor Seri',
            'Jumlah',
            'Satuan',
            'Tahun Perolehan',
            'Sumber Dana',
            'Gedung',
            'Ruangan',
            'PIC',
            'Kondisi',
            'Harga Perolehan',
            'Keterangan',
        ];

        $exampleRow = [
            '1',
            'INV-000001',
            'Peralatan Praktikum',
            'Komputer',
            'Laptop',
            'Lenovo ThinkPad X1',
            'SN-ABC123',
            '1',
            'Unit',
            '2024',
            'Yayasan',
            'Gedung Rektorat',
            'Ruang B1',
            'John Doe',
            'Aktif / Digunakan',
            '8500000',
            'Laptop untuk laboratorium komputer',
        ];

        $callback = function () use ($headers, $exampleRow) {
            $handle = fopen('php://output', 'w');

            // BOM untuk Excel agar UTF-8 terbaca dengan benar
            fwrite($handle, "\xEF\xBB\xBF");

            // Header row
            fputcsv($handle, $headers);

            // Contoh data
            fputcsv($handle, $exampleRow);

            fclose($handle);
        };

        $filename = 'template_import_asset_' . date('Ymd') . '.csv';

        return response()->streamDownload($callback, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
