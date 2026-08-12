<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Employee;
use App\Models\Location;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        // 1. Buat Data Kampus
        $campuses = [];
        $campusNames = ['Utama', 'Cabang A', 'Cabang B'];
        foreach ($campusNames as $name) {
            $campuses[] = Campus::create([
                'name' => 'Kampus ' . $name,
                'address' => $faker->address,
            ]);
        }

        // 2. Buat Data Lokasi/Ruangan
        $locations = [];
        $types = ['Ruang Kelas', 'Laboratorium', 'Kantor', 'Gudang', 'Perpustakaan'];
        foreach ($campuses as $campus) {
            for ($i = 1; $i <= 5; $i++) {
                $locations[] = Location::create([
                    'campus_id' => $campus->id,
                    'name' => $faker->randomElement($types) . ' ' . $faker->numerify('##'),
                    'type' => $faker->randomElement($types),
                    'notes' => 'Lokasi otomatis dibuat.',
                ]);
            }
        }

        // 3. Buat Data Pegawai (PIC)
        $employees = [];
        $departments = ['IT', 'Keuangan', 'Akademik', 'Sarpras', 'HRD'];
        for ($i = 1; $i <= 10; $i++) {
            $employees[] = Employee::create([
                'name' => $faker->name,
                'employee_number' => 'EMP-' . $faker->unique()->numerify('####'),
                'department' => $faker->randomElement($departments),
            ]);
        }

        // 4. Ambil Kategori yang sudah ada
        $categories = Category::all();
        if ($categories->isEmpty()) {
            $categories = collect([Category::create(['name' => 'IT Equipment'])]);
        }

        // 5. Buat Data Aset (50 Data)
        $statuses = ['active', 'stock', 'borrowed', 'maintenance', 'broken'];
        $ownerships = ['company', 'grant', 'loan'];
        
        $assetNames = [
            'Laptop Lenovo Thinkpad', 'PC Desktop Dell', 'Proyektor Epson', 
            'Meja Dosen', 'Kursi Mahasiswa', 'AC Daikin 2 PK', 'Router Mikrotik',
            'Server HP ProLiant', 'Printer Brother', 'Papan Tulis Kaca',
            'Lemari Arsip', 'Mobil Operasional Avanza', 'Motor Dinas Vario'
        ];

        for ($i = 1; $i <= 50; $i++) {
            // Setup relasi random
            $campus = $faker->randomElement($campuses);
            $location = collect($locations)->where('campus_id', $campus->id)->random();
            $pic = $faker->randomElement($employees);

            $asset = Asset::create([
                'inventory_number' => 'INV/' . date('Y') . '/' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'name' => $faker->randomElement($assetNames) . ' ' . $faker->regexify('[A-Z0-9]{3}'),
                'category_id' => $categories->random()->id,
                'serial_number' => $faker->bothify('SN-????-####'),
                'status' => $faker->randomElement($statuses),
                'ownership' => $faker->randomElement($ownerships),
                'campus_id' => $campus->id,
                'location_id' => $location->id,
                'pic_id' => $pic->id,
                'barcode' => $faker->unique()->ean13,
                'notes' => 'Aset hasil generate otomatis.',
            ]);

            // Data Pembelian
            $asset->purchase()->create([
                'purchase_date' => $faker->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
                'unit_price' => $faker->randomFloat(2, 1000000, 20000000),
                'total_price' => $faker->randomFloat(2, 1000000, 20000000),
                'invoice_number' => 'INV-' . $faker->numerify('######'),
            ]);

            // Data Finansial / Nilai Aset
            $acquisition = $faker->randomFloat(2, 1000000, 20000000);
            $asset->financial()->create([
                'acquisition_cost' => $acquisition,
                'book_value' => $acquisition * 0.8,
                'useful_life' => $faker->numberBetween(12, 60), // bulan
            ]);
        }
    }
}
