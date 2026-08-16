<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan field brand (Merk/Tipe) dan unit (Satuan) ke tabel assets.
     * Field lama tidak diubah atau dihapus.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Field brand: Merk/Tipe barang (nullable, backward-compatible)
            $table->string('brand')->nullable()->after('name');

            // Field unit: Satuan barang, misal Unit, Pcs, Set, dll (nullable, backward-compatible)
            $table->string('unit')->nullable()->after('brand');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['brand', 'unit']);
        });
    }
};
