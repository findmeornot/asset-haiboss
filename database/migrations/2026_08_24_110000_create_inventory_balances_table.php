<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('name'); // Since we have no items table, we group by name
            $table->foreignId('campus_id')->nullable()->constrained('campuses')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->integer('quantity')->default(0);
            $table->timestamps();

            // Compound unique key
            $table->unique(['category_id', 'name', 'location_id']);
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->foreignId('inventory_balance_id')->nullable()->after('id')->constrained('inventory_balances')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropForeign(['inventory_balance_id']);
            $table->dropColumn('inventory_balance_id');
        });
        Schema::dropIfExists('inventory_balances');
    }
};
