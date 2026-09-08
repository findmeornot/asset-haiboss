<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ganti nama dari 'Persediaan Barang' / 'Persediaan' menjadi 'Barang Habis Pakai'
        DB::table('classifications')
            ->whereIn('slug', ['persediaan-barang', 'persediaan'])
            ->update([
                'name' => 'Barang Habis Pakai',
                'slug' => Str::slug('Barang Habis Pakai'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('classifications')
            ->where('slug', 'barang-habis-pakai')
            ->update([
                'name' => 'Persediaan Barang',
                'slug' => 'persediaan-barang',
            ]);
    }
};
