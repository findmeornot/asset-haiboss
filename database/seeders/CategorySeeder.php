<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Peralatan Praktikum',
            'Peralatan Kuliah',
            'Kendaraan',
            'Bangunan',
            'Tanah',
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['name' => $category],
                [
                    'code' => strtoupper(Str::slug($category, '-')),
                    'description' => 'Kategori otomatis untuk ' . $category,
                    'active' => true,
                ]
            );
        }
    }
}
