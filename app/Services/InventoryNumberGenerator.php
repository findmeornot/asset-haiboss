<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Asset;
use Exception;

class InventoryNumberGenerator
{
    /**
     * Generate a unique inventory number for a new asset using a concurrency-safe sequence.
     */
    public static function generate(): string
    {
        return DB::transaction(function () {
            // Lock the sequence row for update
            $sequenceRecord = DB::table('inventory_number_sequences')
                ->where('name', 'asset_inventory')
                ->lockForUpdate()
                ->first();

            if (!$sequenceRecord) {
                throw new Exception("Inventory sequence record not found.");
            }

            $sequence = $sequenceRecord->current_value + 1;

            $inventoryNumber = sprintf('INV-%06d', $sequence);

            // Ensure uniqueness (in case of manual insertions bypassing sequence)
            while (Asset::where('inventory_number', $inventoryNumber)->exists()) {
                $sequence++;
                $inventoryNumber = sprintf('INV-%06d', $sequence);
            }

            // Update the sequence
            DB::table('inventory_number_sequences')
                ->where('name', 'asset_inventory')
                ->update(['current_value' => $sequence, 'updated_at' => now()]);

            return $inventoryNumber;
        });
    }
}
