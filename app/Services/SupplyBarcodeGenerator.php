<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\InventoryBalance;

class SupplyBarcodeGenerator
{
    /**
     * Generate a unique Master Barcode for a new InventoryBalance.
     * Format: SUP-XXXXX (e.g., SUP-00001).
     */
    public static function generateMaster(): string
    {
        return DB::transaction(function () {
            $sequenceName = 'master_barcode_seq';

            DB::table('supply_master_sequences')->upsert(
                ['name' => $sequenceName, 'current_value' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['name'],
                ['updated_at']
            );

            $seqRow = DB::table('supply_master_sequences')
                ->where('name', $sequenceName)
                ->lockForUpdate()
                ->first();

            $sequence = $seqRow->current_value + 1;

            DB::table('supply_master_sequences')
                ->where('name', $sequenceName)
                ->update(['current_value' => $sequence]);

            $masterBarcode = sprintf('SUP-%05d', $sequence);

            // Ensure uniqueness just in case
            while (InventoryBalance::where('master_barcode', $masterBarcode)->exists()) {
                $sequence++;
                DB::table('supply_master_sequences')
                    ->where('name', $sequenceName)
                    ->update(['current_value' => $sequence]);
                $masterBarcode = sprintf('SUP-%05d', $sequence);
            }

            return $masterBarcode;
        });
    }

    /**
     * Generate Sub-barcodes for an InventoryBalance.
     * 
     * @return string[] Array of generated sub-barcodes.
     */
    public static function generateSub(InventoryBalance $balance, int $qty): array
    {
        if ($qty <= 0) {
            return [];
        }

        return DB::transaction(function () use ($balance, $qty) {
            // Re-fetch with pessimistic lock to prevent concurrent sequence updates
            $lockedBalance = InventoryBalance::where('id', $balance->id)->lockForUpdate()->first();
            
            $startSequence = $lockedBalance->latest_sequence + 1;
            $endSequence = $lockedBalance->latest_sequence + $qty;
            
            $subBarcodes = [];
            for ($i = $startSequence; $i <= $endSequence; $i++) {
                $subBarcodes[] = sprintf('%s-%04d', $lockedBalance->master_barcode, $i);
            }

            $lockedBalance->update([
                'latest_sequence' => $endSequence
            ]);

            return $subBarcodes;
        });
    }
}
