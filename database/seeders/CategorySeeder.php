<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Classification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // Kategori + relasi klasifikasi berdasarkan struktur akuntansi
        // ASET, INVENTARIS, PERSEDIAAN
        $categoryClassifications = [
            // ─── ASET ───────────────────────────────────────────────
            'Elektronik'                          => ['Aset', 'Inventaris'],
            'Mesin'                               => ['Aset', 'Inventaris'],

            // ─── INVENTARIS ─────────────────────────────────────────
            'ATK'                                 => ['Persediaan Barang'],
            'Dekorasi'                            => ['Inventaris', 'Persediaan Barang'],
            'Elektronik Lainnya'                  => ['Inventaris', 'Persediaan Barang'],
            'Furniture'                           => ['Inventaris'],
            'Jaringan'                            => ['Inventaris'],
            'Mainan'                              => ['Inventaris', 'Persediaan Barang'],
            'Perlengkapan Mushola'                => ['Inventaris'],
            'Perlengkapan Asrama'                 => ['Inventaris', 'Persediaan Barang'],
            'Perlengkapan ATK'                    => ['Inventaris', 'Persediaan Barang'],
            'Perlengkapan Dapur'                  => ['Inventaris', 'Persediaan Barang'],
            'Perlengkapan Ikan'                   => ['Inventaris', 'Persediaan Barang'],
            'Perlengkapan Jaringan'               => ['Inventaris'],
            'Perlengkapan Kamar Mandi'            => ['Inventaris', 'Persediaan Barang'],
            'Perlengkapan Keamanan/Keselamatan'   => ['Inventaris'],
            'Perlengkapan Kebersihan'             => ['Inventaris', 'Persediaan Barang'],
            'Perlengkapan Kendaraan'              => ['Inventaris'],
            'Perlengkapan Kesehatan'              => ['Inventaris'],
            'Perlengkapan Konten'                 => ['Inventaris'],
            'Perlengkapan Lainnya'                => ['Inventaris', 'Persediaan Barang'],
            'Perlengkapan Listrik'                => ['Inventaris'],
            'Perlengkapan Olahraga'               => ['Inventaris', 'Persediaan Barang'],
            'Perlengkapan Tukang'                 => ['Inventaris'],

            // ─── PERSEDIAAN ─────────────────────────────────────────
            'Makanan'                             => ['Persediaan Barang'],
            'Perlengkapan Aksesoris HP'           => ['Persediaan Barang'],
            'Perlengkapan Billiard'               => ['Persediaan Barang'],
            'Perlengkapan PMB'                    => ['Persediaan Barang'],
            'Souvenir'                            => ['Persediaan Barang'],
        ];

        $classifications = Classification::pluck('id', 'name');

        foreach ($categoryClassifications as $name => $classificationNames) {
            $category = Category::firstOrCreate(
                ['name' => $name],
                [
                    'code'        => strtoupper(Str::slug($name, '-')),
                    'description' => 'Kategori otomatis untuk ' . $name,
                    'active'      => true,
                ]
            );

            $ids = collect($classificationNames)
                ->map(fn ($classificationName) => $classifications[$classificationName] ?? null)
                ->filter()
                ->all();

            $category->classifications()->syncWithoutDetaching($ids);
        }
    }
}
