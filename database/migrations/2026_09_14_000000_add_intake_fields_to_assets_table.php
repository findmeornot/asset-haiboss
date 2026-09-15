<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lapor Barang Datang tidak pakai table terpisah — reuse `assets`.
 * OB cuma lapor foto_resi + keterangan + lokasi sementara (campus/location),
 * jadi field identitas barang (inventory_number, name, category_id, ownership)
 * yang tadinya wajib harus dilonggarkan jadi nullable dulu; admin lengkapi
 * belakangan pas proses penempatan. `->change()` butuh doctrine/dbal (tidak
 * terpasang), jadi pakai raw MODIFY COLUMN — aman, tipe & FK existing tetap sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE assets MODIFY inventory_number VARCHAR(255) NULL');
        DB::statement('ALTER TABLE assets MODIFY name VARCHAR(255) NULL');
        DB::statement('ALTER TABLE assets MODIFY category_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE assets MODIFY status VARCHAR(255) NULL');
        DB::statement('ALTER TABLE assets MODIFY ownership VARCHAR(255) NULL');

        Schema::table('assets', function (Blueprint $table) {
            // Diisi OB saat Lapor Barang Datang.
            $table->string('foto_resi')->nullable()->after('notes');
            $table->text('keterangan')->nullable()->after('foto_resi');
            $table->foreignId('reported_by')->nullable()->after('keterangan')
                ->constrained('users')->nullOnDelete();

            // Penempatan sementara oleh OB — belum tentu final, admin yang konfirmasi.
            $table->boolean('location_confirmed')->default(false)->after('reported_by');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reported_by');
            $table->dropColumn(['foto_resi', 'keterangan', 'location_confirmed']);
        });

        DB::statement("ALTER TABLE assets MODIFY inventory_number VARCHAR(255) NOT NULL");
        DB::statement("ALTER TABLE assets MODIFY name VARCHAR(255) NOT NULL");
        DB::statement("ALTER TABLE assets MODIFY category_id BIGINT UNSIGNED NOT NULL");
        DB::statement("ALTER TABLE assets MODIFY status VARCHAR(255) NOT NULL");
        DB::statement("ALTER TABLE assets MODIFY ownership VARCHAR(255) NOT NULL");
    }
};
