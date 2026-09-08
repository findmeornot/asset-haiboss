<?php

namespace Database\Seeders;

use App\Models\Classification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ClassificationSeeder extends Seeder
{
    public function run(): void
    {
        $classifications = ['Aset', 'Inventaris', 'Barang Habis Pakai'];

        foreach ($classifications as $name) {
            Classification::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        }
    }
}
