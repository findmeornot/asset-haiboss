<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Business Rule Final: is_capitalized = unit_price >= 1000000
        // Fix legacy data that was incorrectly migrated using total_price.
        
        $purchaseItems = DB::table('purchase_items')->get();
        
        foreach ($purchaseItems as $item) {
            $unitPrice = $item->unit_price ?? 0;
            
            // Just in case unit_price is 0 but total_price and quantity exist
            // (this shouldn't happen based on the previous migration's fix, but to be safe)
            if ($unitPrice == 0 && $item->total_price > 0 && $item->quantity > 0) {
                $unitPrice = $item->total_price / $item->quantity;
            }
            
            $isCapitalized = $unitPrice >= 1000000;
            
            if ($item->is_capitalized != $isCapitalized) {
                DB::table('purchase_items')
                    ->where('id', $item->id)
                    ->update(['is_capitalized' => $isCapitalized]);
            }
        }
    }

    public function down(): void
    {
        // We cannot reliably revert this correction to the WRONG state,
        // because we don't know which ones were wrong before.
        // Doing nothing is the safest down() for this correction.
    }
};
