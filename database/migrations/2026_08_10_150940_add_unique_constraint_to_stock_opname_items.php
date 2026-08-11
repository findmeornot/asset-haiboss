<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // First, ensure there are no existing duplicates. If there are, keep the one with the latest checked_at or the highest ID.
        // For safety, we can delete the duplicates.
        DB::statement('
            DELETE t1 FROM stock_opname_items t1
            INNER JOIN stock_opname_items t2 
            WHERE 
                t1.id < t2.id AND 
                t1.stock_opname_id = t2.stock_opname_id AND 
                t1.asset_id = t2.asset_id
        ');

        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->unique(['stock_opname_id', 'asset_id'], 'stock_opname_asset_unique');
        });
    }

    public function down(): void
    {
        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->dropUnique('stock_opname_asset_unique');
        });
    }
};
