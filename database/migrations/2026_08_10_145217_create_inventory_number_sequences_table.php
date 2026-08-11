<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedBigInteger('current_value')->default(0);
            $table->timestamps();
        });

        // Seed initial value based on current maximum asset inventory number
        $latestAsset = DB::table('assets')->orderBy('id', 'desc')->first();
        $sequence = 0;
        if ($latestAsset && preg_match('/INV-(\d+)/', $latestAsset->inventory_number, $matches)) {
            $sequence = intval($matches[1]);
        }
        
        DB::table('inventory_number_sequences')->insert([
            'name' => 'asset_inventory',
            'current_value' => $sequence,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_number_sequences');
    }
};
