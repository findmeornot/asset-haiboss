<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Support\Facades\DB;

class BarcodeNumberGenerator
{
    /**
     * Generate a unique, permanent, sequential 6-digit barcode number.
     * This is strictly a numeric identifier (e.g. 000123) and is distinct from SKU.
     */
    public static function generate(): string
    {
        return DB::transaction(function () {
            // Get the highest barcode number that consists only of digits
            // We use raw queries here for safety depending on db driver, but simple ordering is fine
            $latestAsset = Asset::withTrashed()
                                ->whereRaw('barcode REGEXP "^[0-9]+$"')
                                ->lockForUpdate()
                                ->orderByRaw('CAST(barcode AS UNSIGNED) DESC')
                                ->first();

            $sequence = 1;
            if ($latestAsset && is_numeric($latestAsset->barcode)) {
                $sequence = (int) $latestAsset->barcode + 1;
            }

            $barcodeNumber = sprintf('%06d', $sequence);

            // Ensure uniqueness just in case
            while (Asset::withTrashed()->where('barcode', $barcodeNumber)->exists()) {
                $sequence++;
                $barcodeNumber = sprintf('%06d', $sequence);
            }

            return $barcodeNumber;
        });
    }
}
