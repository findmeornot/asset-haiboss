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
        $categoryClassifications = [
            'Elektronik'                          => ['Aset', 'Inventaris'],
            'Mesin'                               => ['Aset', 'Inventaris'],
            
            'ATK'                                 => ['Inventaris', 'Barang Habis Pakai', 'Barang Habis Pakai'],
            'Dekorasi'                            => ['Inventaris', 'Barang Habis Pakai', 'Barang Habis Pakai'],
            'Elektronik Lainnya'                  => ['Inventaris', 'Barang Habis Pakai', 'Barang Habis Pakai'],
            'Furniture'                           => ['Inventaris'],
            'Jaringan'                            => ['Inventaris'],
            'Mainan'                              => ['Inventaris', 'Barang Habis Pakai', 'Barang Habis Pakai'],
            'Perlengkapan Mushola'                => ['Inventaris'],
            'Perlengkapan Asrama'                 => ['Inventaris', 'Barang Habis Pakai', 'Barang Habis Pakai'],
            'Perlengkapan ATK'                    => ['Inventaris', 'Barang Habis Pakai', 'Barang Habis Pakai'],
            'Perlengkapan Dapur'                  => ['Inventaris', 'Barang Habis Pakai', 'Barang Habis Pakai'],
            'Perlengkapan Ikan'                   => ['Inventaris', 'Barang Habis Pakai', 'Barang Habis Pakai'],
            'Perlengkapan Jaringan'               => ['Inventaris'],
            'Perlengkapan Kamar Mandi'            => ['Inventaris', 'Barang Habis Pakai', 'Barang Habis Pakai'],
            'Perlengkapan Keamanan/Keselamatan'   => ['Inventaris'],
            'Perlengkapan Kebersihan'             => ['Inventaris', 'Barang Habis Pakai', 'Barang Habis Pakai'],
            'Perlengkapan Kendaraan'              => ['Inventaris'],
            'Perlengkapan Kesehatan'              => ['Inventaris'],
            'Perlengkapan Konten'                 => ['Inventaris'],
            'Perlengkapan Lainnya'                => ['Inventaris', 'Barang Habis Pakai', 'Barang Habis Pakai'],
            'Perlengkapan Listrik'                => ['Inventaris'],
            'Perlengkapan Olahraga'               => ['Inventaris', 'Barang Habis Pakai', 'Barang Habis Pakai'],
            'Perlengkapan Tukang'                 => ['Inventaris'],
            
            'Makanan'                             => ['Barang Habis Pakai', 'Barang Habis Pakai'],
            'Perlengkapan Aksesoris HP'           => ['Barang Habis Pakai', 'Barang Habis Pakai'],
            'Perlengkapan Billiard'               => ['Barang Habis Pakai', 'Barang Habis Pakai'],
            'Perlengkapan PMB'                    => ['Barang Habis Pakai', 'Barang Habis Pakai'],
            'Souvenir'                            => ['Barang Habis Pakai', 'Barang Habis Pakai'],
        ];

        // Mendapatkan semua classification yang ada (termasuk 'Barang Habis Pakai' atau 'Barang Habis Pakai')
        $classifications = Classification::pluck('id', 'name');

        foreach ($categoryClassifications as $name => $classificationNames) {
            $category = Category::firstOrCreate(
                ['name' => strtoupper($name)],
                [
                    'code'        => strtoupper(Str::slug($name, '-')),
                    'description' => 'Kategori otomatis untuk ' . $name,
                    'active'      => true,
                    'type'        => 'asset', // Default, bisa disesuaikan nanti
                ]
            );

            $ids = collect($classificationNames)
                ->map(fn ($classificationName) => $classifications[$classificationName] ?? null)
                ->filter()
                ->unique()
                ->all();

            $category->classifications()->syncWithoutDetaching($ids);
        }
    }
}
