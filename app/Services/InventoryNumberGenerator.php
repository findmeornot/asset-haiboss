<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Asset;

class InventoryNumberGenerator
{
    private const PREFIX = 'INV';

    /**
     * Normalize a manual (fallback) inventory input into the canonical INV-prefixed format.
     *
     * Accepts any of these forms typed by a user when the USB scanner is unavailable:
     *   - Digits only:           "1945"    → "INV0001945"
     *   - Digits with prefix:    "INV1945" → "INV0001945"
     *   - Already canonical:     "INV0001945" → "INV0001945"
     *
     * Returns null when the input is empty or contains no numeric part.
     * The returned value can be passed directly to Asset::where('inventory_number', ...) for
     * an exact-match search — no LIKE, no partial matching.
     */
    public static function normalizeManualInput(string $input): ?string
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        // Strip the INV prefix (case-insensitive) if present, leaving only digits.
        $digits = preg_replace('/^' . preg_quote(self::PREFIX, '/') . '/i', '', $input);

        // After stripping the prefix, we expect a pure numeric string.
        if (!ctype_digit($digits) || $digits === '') {
            return null;
        }

        return sprintf('%s%07d', self::PREFIX, (int) $digits);
    }

    /**
     * Generate a unique inventory number for a new asset (e.g. INV0000001).
     * Now acts as a permanent unique Kode Barang, independent of category.
     */
    public static function generate(): string
    {
        return DB::transaction(function () {
            // Use upsert to handle concurrent first inserts safely
            DB::table('inventory_number_sequences')->upsert(
                [
                    'name' => self::PREFIX,
                    'current_value' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                ['name'],
                // Do not update current_value if it exists, just update updated_at
                ['updated_at']
            );

            // Now row is guaranteed to exist, lock it
            $seqRow = DB::table('inventory_number_sequences')
                ->where('name', self::PREFIX)
                ->lockForUpdate()
                ->first();

            $sequence = $seqRow->current_value + 1;

            // Just in case it's 1 and there are legacy items not tracked in sequence table
            if ($sequence === 1) {
                // Find the latest asset that matches the INV[0-9]{7} format, including soft deleted ones
                $latestAsset = Asset::withTrashed()
                                    ->whereRaw('inventory_number REGEXP "^' . self::PREFIX . '[0-9]+$"')
                                    ->orderByRaw('CAST(SUBSTRING(inventory_number, ' . (strlen(self::PREFIX) + 1) . ') AS UNSIGNED) DESC')
                                    ->first();
                if ($latestAsset) {
                    $lastPart = substr($latestAsset->inventory_number, strlen(self::PREFIX));
                    if (is_numeric($lastPart)) {
                        $sequence = (int) $lastPart + 1;
                    }
                }
            }

            DB::table('inventory_number_sequences')
                ->where('name', self::PREFIX)
                ->update(['current_value' => $sequence]);

            $inventoryNumber = sprintf('%s%07d', self::PREFIX, $sequence);

            // Ensure uniqueness considering soft deleted records as well
            while (Asset::withTrashed()->where('inventory_number', $inventoryNumber)->exists()) {
                $sequence++;
                DB::table('inventory_number_sequences')
                    ->where('name', self::PREFIX)
                    ->update(['current_value' => $sequence]);
                $inventoryNumber = sprintf('%s%07d', self::PREFIX, $sequence);
            }

            return $inventoryNumber;
        });
    }

    /**
     * Generate an array of unique inventory numbers in bulk for performance.
     */
    public static function generateBulk(int $qty = 1): array
    {
        if ($qty <= 0) return [];

        return DB::transaction(function () use ($qty) {
            DB::table('inventory_number_sequences')->upsert(
                ['name' => self::PREFIX, 'current_value' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['name'], ['updated_at']
            );

            $seqRow = DB::table('inventory_number_sequences')->where('name', self::PREFIX)->lockForUpdate()->first();
            $sequence = $seqRow->current_value + 1;

            if ($sequence === 1) {
                $latestAsset = Asset::withTrashed()
                                    ->whereRaw('inventory_number REGEXP "^' . self::PREFIX . '[0-9]+$"')
                                    ->orderByRaw('CAST(SUBSTRING(inventory_number, ' . (strlen(self::PREFIX) + 1) . ') AS UNSIGNED) DESC')
                                    ->first();
                if ($latestAsset) {
                    $lastPart = substr($latestAsset->inventory_number, strlen(self::PREFIX));
                    if (is_numeric($lastPart)) {
                        $sequence = (int) $lastPart + 1;
                    }
                }
            }

            // Fetch all existing sequences for this prefix to avoid hitting DB in a loop
            // Make sure to include soft deleted assets!
            $existingNumbers = Asset::withTrashed()
                                    ->where('inventory_number', 'like', self::PREFIX . "%")
                                    ->pluck('inventory_number')
                                    ->flip()
                                    ->toArray();

            $generated = [];
            while (count($generated) < $qty) {
                $candidate = sprintf('%s%07d', self::PREFIX, $sequence);
                if (!isset($existingNumbers[$candidate])) {
                    $generated[] = $candidate;
                }
                $sequence++;
            }

            // Update sequence table to the last checked sequence
            DB::table('inventory_number_sequences')
                ->where('name', self::PREFIX)
                ->update(['current_value' => $sequence - 1]);

            return $generated;
        });
    }
}
