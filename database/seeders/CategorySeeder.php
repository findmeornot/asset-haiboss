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
        $legacyCategories = [
            'Peralatan Praktikum',
            'Peralatan Kuliah',
            'Kendaraan',
            'Bangunan',
            'Tanah',
        ];

        foreach ($legacyCategories as $category) {
            Category::firstOrCreate(
                ['name' => $category],
                [
                    'code' => strtoupper(Str::slug($category, '-')),
                    'description' => 'Kategori otomatis untuk ' . $category,
                    'active' => true,
                ]
            );
        }

        // Kategori + relasi klasifikasi (many-to-many): satu kategori boleh dipakai lebih dari satu klasifikasi.
        $categoryClassifications = [
            'Elektronik' => ['Aset', 'Inventaris', 'Persediaan Barang'],
            'Mesin' => ['Aset', 'Inventaris'],
            'Listrik' => ['Persediaan Barang'],
            'Jaringan' => ['Inventaris', 'Persediaan Barang'],
            'Furniture/Dekorasi' => ['Aset', 'Inventaris'],
            'ATK' => ['Persediaan Barang'],
            'Kebersihan' => ['Persediaan Barang'],
            'Souvenir' => ['Persediaan Barang'],
            'Perlengkapan Lainnya' => ['Persediaan Barang'],
            'Perlengkapan Pancing' => ['Persediaan Barang'],
            'Perlengkapan Kendaraan' => ['Inventaris', 'Persediaan Barang'],
            'Perlengkapan Tukang' => ['Inventaris', 'Persediaan Barang'],
            'Perlengkapan Ikan' => ['Persediaan Barang'],
            'Perlengkapan PMB' => ['Persediaan Barang'],
            'Asrama' => ['Inventaris', 'Persediaan Barang'],
            'Dapur' => ['Inventaris', 'Persediaan Barang'],
            'Mainan' => ['Persediaan Barang'],
            'Kamar Mandi' => ['Persediaan Barang'],
            'Aksesoris HP' => ['Persediaan Barang'],
            'Olahraga' => ['Inventaris', 'Persediaan Barang'],
        ];

        $classifications = Classification::pluck('id', 'name');

        foreach ($categoryClassifications as $name => $classificationNames) {
            $category = Category::firstOrCreate(
                ['name' => $name],
                [
                    'code' => strtoupper(Str::slug($name, '-')),
                    'description' => 'Kategori otomatis untuk ' . $name,
                    'active' => true,
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
