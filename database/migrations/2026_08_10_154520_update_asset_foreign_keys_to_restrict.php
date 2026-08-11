<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'asset_status_histories',
            'asset_location_histories',
            'asset_price_histories',
            'asset_movements',
            'stock_opname_items',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropForeign(['asset_id']); 
                // wait, dropping by column name array uses standard naming convention.
            });
            
            Schema::table($tableName, function (Blueprint $table) {
                // Change to RESTRICT
                $table->foreign('asset_id')->references('id')->on('assets')->restrictOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'asset_status_histories',
            'asset_location_histories',
            'asset_price_histories',
            'asset_movements',
            'stock_opname_items',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['asset_id']);
            });

            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('asset_id')->references('id')->on('assets')->cascadeOnDelete();
            });
        }
    }
};
