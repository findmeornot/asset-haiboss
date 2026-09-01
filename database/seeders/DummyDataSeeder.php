<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Employee;
use App\Models\Location;
use Illuminate\Database\Seeder;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat Data Gedung
        $campusData = [
            ['name' => 'Kampus Utama',    'address' => 'Jl. Raya Utama No. 1, Jakarta'],
            ['name' => 'Kampus Cabang A', 'address' => 'Jl. Sudirman No. 45, Surabaya'],
            ['name' => 'Kampus Cabang B', 'address' => 'Jl. Diponegoro No. 12, Bandung'],
        ];
        $campuses = [];
        foreach ($campusData as $data) {
            $campuses[] = Campus::firstOrCreate(['name' => $data['name']], $data);
        }

        // 2. Buat Data Lokasi/Ruangan
        $locations   = [];
        $roomTypes   = ['Ruang Kelas', 'Laboratorium', 'Kantor', 'Gudang', 'Perpustakaan'];
        foreach ($campuses as $campus) {
            for ($i = 1; $i <= 5; $i++) {
                $type        = $roomTypes[($i - 1) % count($roomTypes)];
                $locations[] = Location::firstOrCreate(
                    ['campus_id' => $campus->id, 'name' => $type . ' ' . str_pad($i, 2, '0', STR_PAD_LEFT)],
                    ['type' => $type, 'notes' => 'Lokasi otomatis dibuat.']
                );
            }
        }

        // 3. Buat Data Pegawai (PIC)
        $employeeData = [
            ['name' => 'Budi Santoso',    'employee_number' => 'EMP-0001', 'department' => 'IT'],
            ['name' => 'Sari Dewi',       'employee_number' => 'EMP-0002', 'department' => 'Keuangan'],
            ['name' => 'Andi Wijaya',     'employee_number' => 'EMP-0003', 'department' => 'Akademik'],
            ['name' => 'Rina Kusuma',     'employee_number' => 'EMP-0004', 'department' => 'Sarpras'],
            ['name' => 'Dodi Pratama',    'employee_number' => 'EMP-0005', 'department' => 'HRD'],
            ['name' => 'Maya Putri',      'employee_number' => 'EMP-0006', 'department' => 'IT'],
            ['name' => 'Hendra Gunawan',  'employee_number' => 'EMP-0007', 'department' => 'Akademik'],
            ['name' => 'Lestari Indah',   'employee_number' => 'EMP-0008', 'department' => 'Keuangan'],
            ['name' => 'Fajar Nugroho',   'employee_number' => 'EMP-0009', 'department' => 'Sarpras'],
            ['name' => 'Wulan Sari',      'employee_number' => 'EMP-0010', 'department' => 'HRD'],
        ];
        $employees = [];
        foreach ($employeeData as $data) {
            $employees[] = Employee::firstOrCreate(['employee_number' => $data['employee_number']], $data);
        }

        // 4. Ambil Kategori yang sudah ada
        $categories = Category::all();
        if ($categories->isEmpty()) {
            $categories = collect([Category::create(['name' => 'IT Equipment', 'type' => 'asset'])]);
        }

        // 5. Buat 50 Data Aset
        $assetNames = [
            'Laptop Lenovo Thinkpad', 'PC Desktop Dell', 'Proyektor Epson',
            'Meja Dosen', 'Kursi Mahasiswa', 'AC Daikin 2 PK', 'Router Mikrotik',
            'Server HP ProLiant', 'Printer Brother', 'Papan Tulis Kaca',
            'Lemari Arsip', 'Mobil Operasional Avanza', 'Motor Dinas Vario',
            'Switch HP 24 Port', 'UPS APC 1000VA', 'Kamera CCTV Hikvision',
            'Scanner Fujitsu', 'Monitor LG 24 inch', 'Keyboard Logitech', 'Mouse Wireless',
        ];
        $statuses   = ['active', 'stock', 'borrowed', 'maintenance'];
        $ownerships = ['company', 'grant', 'loan'];

        for ($i = 1; $i <= 50; $i++) {
            $campus   = $campuses[$i % count($campuses)];
            $campusLocs = collect($locations)->where('campus_id', $campus->id)->values();
            $location = $campusLocs->isNotEmpty() ? $campusLocs[$i % $campusLocs->count()] : $locations[0];
            $pic      = $employees[$i % count($employees)];
            $category = $categories[$i % $categories->count()];
            $name     = $assetNames[$i % count($assetNames)];
            $status   = $statuses[$i % count($statuses)];
            $ownership = $ownerships[$i % count($ownerships)];
            $unitPrice = (($i % 20) + 1) * 1_000_000;

            // Skip jika inventory_number sudah ada
            $invNumber = 'INV/' . date('Y') . '/' . str_pad($i, 4, '0', STR_PAD_LEFT);
            if (Asset::where('inventory_number', $invNumber)->exists()) {
                continue;
            }

            $asset = Asset::create([
                'inventory_number' => $invNumber,
                'name'             => $name . ' ' . strtoupper(substr(md5($i), 0, 3)),
                'category_id'      => $category->id,
                'classification_id'=> $category->classifications()->first()?->id,
                'serial_number'    => 'SN-' . strtoupper(substr(md5('sn' . $i), 0, 8)),
                'status'           => $status,
                'ownership'        => $ownership,
                'campus_id'        => $campus->id,
                'location_id'      => $location->id,
                'pic_id'           => $pic->id,
                'notes'            => 'Aset hasil generate otomatis.',
            ]);

            // Data Pembelian
            $asset->purchase()->firstOrCreate([], [
                'purchase_date'  => now()->subMonths(($i % 60) + 1)->format('Y-m-d'),
                'unit_price'     => $unitPrice,
                'quantity'       => 1,
                'total_price'    => $unitPrice,
                'invoice_number' => 'INV-' . str_pad($i, 6, '0', STR_PAD_LEFT),
            ]);

            // Data Finansial
            $asset->financial()->firstOrCreate([], [
                'acquisition_cost' => $unitPrice,
                'book_value'       => $unitPrice * 0.8,
                'useful_life'      => (($i % 4) + 1) * 12,
            ]);
        }
    }
}
