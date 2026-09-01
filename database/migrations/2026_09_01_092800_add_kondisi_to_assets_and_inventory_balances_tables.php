<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (!Schema::hasColumn('assets', 'kondisi')) {
                $table->string('kondisi')->nullable()->after('status');
            }
        });

        Schema::table('inventory_balances', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_balances', 'kondisi')) {
                $table->string('kondisi')->nullable()->after('status');
            }
        });

        Schema::table('asset_status_histories', function (Blueprint $table) {
            if (!Schema::hasColumn('asset_status_histories', 'old_kondisi')) {
                $table->string('old_kondisi')->nullable()->after('old_status');
            }
            if (!Schema::hasColumn('asset_status_histories', 'new_kondisi')) {
                $table->string('new_kondisi')->nullable()->after('new_status');
            }
        });

        // Migrate data for assets
        DB::table('assets')->where('status', 'stock')->update([
            'kondisi' => 'good',
        ]);
        
        DB::table('assets')->where('status', 'minor_damage')->update([
            'status' => 'stock', 
            'kondisi' => 'minor_damage',
        ]);
        
        DB::table('assets')->where('status', 'major_damage')->update([
            'status' => 'stock',
            'kondisi' => 'major_damage',
        ]);
        
        DB::table('assets')->whereNull('kondisi')->update([
            'kondisi' => 'good',
        ]);

        // Migrate data for inventory_balances
        DB::table('inventory_balances')->where('status', 'stock')->update([
            'kondisi' => 'good',
        ]);
        
        DB::table('inventory_balances')->where('status', 'minor_damage')->update([
            'status' => 'stock',
            'kondisi' => 'minor_damage',
        ]);
        
        DB::table('inventory_balances')->where('status', 'major_damage')->update([
            'status' => 'stock',
            'kondisi' => 'major_damage',
        ]);

        DB::table('inventory_balances')->whereNull('kondisi')->update([
            'kondisi' => 'good',
        ]);
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (Schema::hasColumn('assets', 'kondisi')) {
                $table->dropColumn('kondisi');
            }
        });

        Schema::table('inventory_balances', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_balances', 'kondisi')) {
                $table->dropColumn('kondisi');
            }
        });

        Schema::table('asset_status_histories', function (Blueprint $table) {
            if (Schema::hasColumn('asset_status_histories', 'old_kondisi')) {
                $table->dropColumn('old_kondisi');
            }
            if (Schema::hasColumn('asset_status_histories', 'new_kondisi')) {
                $table->dropColumn('new_kondisi');
            }
        });
    }
};
