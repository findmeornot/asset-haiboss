<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu laporan OB (1 resi/box) bisa berisi banyak jenis barang & banyak unit.
 * Laporan tetap 1 row Asset (`reported_by` terisi); unit lain hasil invoice
 * Finance menunjuk ke laporan lewat `intake_parent_id` dan `reported_by`-nya
 * null, jadi list Barang Masuk tetap 1 row per laporan sementara antrean
 * penempatan berisi semua unit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('intake_parent_id')->nullable()->after('reported_by')
                ->constrained('assets')->nullOnDelete();
        });

        // Backfill hasil fan-out versi lama: clone ikut membawa reported_by.
        // Per purchase_item, row dengan id terkecil dianggap laporan asli.
        $groups = DB::table('assets')
            ->whereNotNull('reported_by')
            ->whereNotNull('purchase_item_id')
            ->groupBy('purchase_item_id')
            ->havingRaw('COUNT(*) > 1')
            ->selectRaw('purchase_item_id, MIN(id) as parent_id')
            ->get();

        foreach ($groups as $group) {
            DB::table('assets')
                ->where('purchase_item_id', $group->purchase_item_id)
                ->whereNotNull('reported_by')
                ->where('id', '<>', $group->parent_id)
                ->update([
                    'intake_parent_id' => $group->parent_id,
                    'reported_by' => null,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('intake_parent_id');
        });
    }
};
