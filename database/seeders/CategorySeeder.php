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
            
            'ATK'                                 => ['Inventaris', 'Persediaan', 'Persediaan Barang'],
            'Dekorasi'                            => ['Inventaris', 'Persediaan', 'Persediaan Barang'],
            'Elektronik Lainnya'                  => ['Inventaris', 'Persediaan', 'Persediaan Barang'],
            'Furniture'                           => ['Inventaris'],
            'Jaringan'                            => ['Inventaris'],
            'Mainan'                              => ['Inventaris', 'Persediaan', 'Persediaan Barang'],
            'Perlengkapan Mushola'                => ['Inventaris'],
            'Perlengkapan Asrama'                 => ['Inventaris', 'Persediaan', 'Persediaan Barang'],
            'Perlengkapan ATK'                    => ['Inventaris', 'Persediaan', 'Persediaan Barang'],
            'Perlengkapan Dapur'                  => ['Inventaris', 'Persediaan', 'Persediaan Barang'],
            'Perlengkapan Ikan'                   => ['Inventaris', 'Persediaan', 'Persediaan Barang'],
            'Perlengkapan Jaringan'               => ['Inventaris'],
            'Perlengkapan Kamar Mandi'            => ['Inventaris', 'Persediaan', 'Persediaan Barang'],
            'Perlengkapan Keamanan/Keselamatan'   => ['Inventaris'],
            'Perlengkapan Kebersihan'             => ['Inventaris', 'Persediaan', 'Persediaan Barang'],
            'Perlengkapan Kendaraan'              => ['Inventaris'],
            'Perlengkapan Kesehatan'              => ['Inventaris'],
            'Perlengkapan Konten'                 => ['Inventaris'],
            'Perlengkapan Lainnya'                => ['Inventaris', 'Persediaan', 'Persediaan Barang'],
            'Perlengkapan Listrik'                => ['Inventaris'],
            'Perlengkapan Olahraga'               => ['Inventaris', 'Persediaan', 'Persediaan Barang'],
            'Perlengkapan Tukang'                 => ['Inventaris'],
            
            'Makanan'                             => ['Persediaan', 'Persediaan Barang'],
            'Perlengkapan Aksesoris HP'           => ['Persediaan', 'Persediaan Barang'],
            'Perlengkapan Billiard'               => ['Persediaan', 'Persediaan Barang'],
            'Perlengkapan PMB'                    => ['Persediaan', 'Persediaan Barang'],
            'Souvenir'                            => ['Persediaan', 'Persediaan Barang'],
        ];

        // Mendapatkan semua classification yang ada (termasuk 'Persediaan Barang' atau 'Persediaan')
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
