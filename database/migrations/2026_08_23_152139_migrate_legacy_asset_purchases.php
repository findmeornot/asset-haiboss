<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Data Migration Strategy: 1 asset_purchase -> 1 purchase -> 1 purchase_item -> 1 asset
        // This is the safest way to ensure no data loss and no incorrect grouping.
        
        $assetPurchases = DB::table('asset_purchases')->get();
        
        foreach ($assetPurchases as $ap) {
            // Get ownership from asset
            $asset = DB::table('assets')->where('id', $ap->asset_id)->first();
            $ownership = $asset ? $asset->ownership : 'company';
            $name = $asset ? $asset->name : 'Unknown Asset';
            $categoryId = $asset ? $asset->category_id : null;
            $classificationId = $asset ? $asset->classification_id : null;
            
            // Determine quantity safely. 
            // The previous audit showed that form input quantity is sometimes unset before save.
            $qty = $ap->quantity ?: 1;
            $totalPrice = $ap->total_price ?? 0;
            $unitPrice = $ap->unit_price ?? 0;
            
            if ($qty > 0 && $totalPrice > 0 && $unitPrice == 0) {
                $unitPrice = $totalPrice / $qty;
            } elseif ($qty > 0 && $unitPrice > 0 && $totalPrice == 0) {
                $totalPrice = $unitPrice * $qty;
            }
            
            $isCapitalized = $totalPrice >= 1000000;
            
            $purchaseId = DB::table('purchases')->insertGetId([
                'invoice_number' => $ap->invoice_number,
                'tax_invoice_number' => $ap->tax_invoice_number,
                'purchase_date' => $ap->purchase_date,
                'ownership' => $ownership,
                'total_amount' => $totalPrice,
                'created_at' => $ap->created_at,
                'updated_at' => $ap->updated_at,
            ]);
            
            $purchaseItemId = DB::table('purchase_items')->insertGetId([
                'purchase_id' => $purchaseId,
                'category_id' => $categoryId,
                'classification_id' => $classificationId,
                'name' => $name,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
                'is_capitalized' => $isCapitalized,
                'created_at' => $ap->created_at,
                'updated_at' => $ap->updated_at,
            ]);
            
            DB::table('assets')
                ->where('id', $ap->asset_id)
                ->update(['purchase_item_id' => $purchaseItemId]);
        }
        
        // We do not drop asset_purchases or ownership from assets in this migration,
        // because we are in Milestone 3 and Filament/Application layer still depends on them!
        // This will be done in a cleanup phase after UI redesign.
    }

    public function down(): void
    {
        // Revert links
        DB::table('assets')->update(['purchase_item_id' => null]);
        
        // Delete migrated data
        DB::table('purchase_items')->delete();
        DB::table('purchases')->delete();
    }
};
