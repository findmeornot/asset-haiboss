<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->foreignId('campus_id')->nullable()->after('status')->constrained('campuses')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->after('campus_id')->constrained('locations')->nullOnDelete();
        });

        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->string('scanned_inventory_number')->nullable()->after('asset_id');
            $table->foreignId('expected_location_id')->nullable()->after('condition')->constrained('locations')->nullOnDelete();
            // We already have location_id in the original migration, let's treat it as actual_location_id and add it if missing
            // Rename is tricky across DBs, so let's just add actual_location_id
            $table->foreignId('actual_location_id')->nullable()->after('expected_location_id')->constrained('locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->dropForeign(['campus_id']);
            $table->dropColumn('campus_id');
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });

        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->dropColumn('scanned_inventory_number');
            $table->dropForeign(['expected_location_id']);
            $table->dropColumn('expected_location_id');
            $table->dropForeign(['actual_location_id']);
            $table->dropColumn('actual_location_id');
        });
    }
};
