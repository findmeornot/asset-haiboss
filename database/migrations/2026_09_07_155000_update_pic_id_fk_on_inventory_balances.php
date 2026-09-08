<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Bersihkan data yatim sebelum menambah FK
        DB::statement('UPDATE inventory_balances SET pic_id = NULL WHERE pic_id IS NOT NULL AND pic_id NOT IN (SELECT id FROM employees)');

        // Ambil nama constraint yang mengikat kolom pic_id
        $constraints = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.key_column_usage WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_balances' AND COLUMN_NAME = 'pic_id'");
        foreach ($constraints as $c) {
            try {
                DB::statement("ALTER TABLE inventory_balances DROP FOREIGN KEY `{\$c->CONSTRAINT_NAME}`");
            } catch (\Exception $e) {
                // Ignore errors
            }
        }
        
        // Hapus index bawaan FK lama jika masih nyangkut
        try {
            DB::statement("ALTER TABLE inventory_balances DROP INDEX `inventory_balances_pic_id_foreign`");
        } catch (\Exception $e) {
            // Ignore errors
        }

        Schema::table('inventory_balances', function (Blueprint $table) {
            // Re-add the foreign key pointing to employees
            $table->foreign('pic_id', 'fk_inv_bal_pic_id')
                  ->references('id')->on('employees')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        // No safe down needed
    }
};
