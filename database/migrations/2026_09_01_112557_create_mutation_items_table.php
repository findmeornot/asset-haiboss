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
        Schema::create('mutation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mutation_id')->constrained('mutations')->cascadeOnDelete();
            
            // For Assets (Individual)
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            
            // For Inventaris & Persediaan
            $table->foreignId('inventory_balance_id')->nullable()->constrained('inventory_balances')->nullOnDelete();
            
            // Transfer quantity (default 1 for Aset)
            $table->integer('quantity')->default(1);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mutation_items');
    }
};
