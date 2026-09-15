<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barcode_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedBigInteger('current_value')->default(0);
            $table->timestamps();
        });

        // Seed initial value from the highest existing numeric barcode, so the
        // counter continues from where legacy full-table-scan generation left off.
        $sequence = 0;
        $latestBarcode = DB::table('assets')
            ->whereRaw('barcode REGEXP "^[0-9]+$"')
            ->orderByRaw('CAST(barcode AS UNSIGNED) DESC')
            ->value('barcode');

        if ($latestBarcode !== null) {
            $sequence = (int) $latestBarcode;
        }

        DB::table('barcode_sequences')->insert([
            'name'          => 'barcode',
            'current_value' => $sequence,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('barcode_sequences');
    }
};
