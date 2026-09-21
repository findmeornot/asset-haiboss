<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto pengecekan barang (alur OB: Lapor Barang Datang -> Pengecekan) diambil
 * per-posisi: tampak depan, tampak samping, label SN, dan kondisi kardus.
 * `photo_type` menandai slot tersebut supaya satu slot bisa ditimpa ulang tanpa
 * menghapus foto posisi lain. Foto lama (upload admin lewat Filament) tetap
 * valid dengan photo_type NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_photos', function (Blueprint $table) {
            $table->string('photo_type')->nullable()->after('asset_id');
            $table->index(['asset_id', 'photo_type']);
        });
    }

    public function down(): void
    {
        Schema::table('asset_photos', function (Blueprint $table) {
            $table->dropIndex(['asset_id', 'photo_type']);
            $table->dropColumn('photo_type');
        });
    }
};
