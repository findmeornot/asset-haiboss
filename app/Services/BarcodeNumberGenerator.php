<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Support\Facades\DB;

class BarcodeNumberGenerator
{
    private const SEQUENCE_NAME = 'barcode';

    /**
     * Generate a unique, permanent, sequential 6-digit barcode number.
     * This is strictly a numeric identifier (e.g. 000123) and is distinct from SKU.
     *
     * Uses a dedicated sequence counter (barcode_sequences) instead of scanning the
     * whole assets table on every call, so generation stays fast as assets grows.
     */
    public static function generate(): string
    {
        return DB::transaction(function () {
            DB::table('barcode_sequences')->upsert(
                [
                    'name' => self::SEQUENCE_NAME,
                    'current_value' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                ['name'],
                ['updated_at']
            );

            $seqRow = DB::table('barcode_sequences')
                ->where('name', self::SEQUENCE_NAME)
                ->lockForUpdate()
                ->first();

            $sequence = $seqRow->current_value + 1;

            // Just in case it's 1 and there are legacy/manually-seeded barcodes not yet
            // reflected in the counter, catch up from the highest existing numeric barcode.
            if ($sequence === 1) {
                $latestAsset = Asset::withTrashed()
                                    ->whereRaw('barcode REGEXP "^[0-9]+$"')
                                    ->orderByRaw('CAST(barcode AS UNSIGNED) DESC')
                                    ->first();
                if ($latestAsset && is_numeric($latestAsset->barcode)) {
                    $sequence = (int) $latestAsset->barcode + 1;
                }
            }

            DB::table('barcode_sequences')
                ->where('name', self::SEQUENCE_NAME)
                ->update(['current_value' => $sequence]);

            $barcodeNumber = sprintf('%06d', $sequence);

            // Ensure uniqueness just in case (indexed exact-match lookup, cheap).
            while (Asset::withTrashed()->where('barcode', $barcodeNumber)->exists()) {
                $sequence++;
                DB::table('barcode_sequences')
                    ->where('name', self::SEQUENCE_NAME)
                    ->update(['current_value' => $sequence]);
                $barcodeNumber = sprintf('%06d', $sequence);
            }

            return $barcodeNumber;
        });
    }

    /**
     * Generate an array of unique barcode numbers in bulk for performance —
     * one locked transaction for the whole batch instead of one per unit.
     */
    public static function generateBulk(int $qty = 1): array
    {
        if ($qty <= 0) return [];

        return DB::transaction(function () use ($qty) {
            DB::table('barcode_sequences')->upsert(
                ['name' => self::SEQUENCE_NAME, 'current_value' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['name'], ['updated_at']
            );

            $seqRow = DB::table('barcode_sequences')->where('name', self::SEQUENCE_NAME)->lockForUpdate()->first();
            $sequence = $seqRow->current_value + 1;

            if ($sequence === 1) {
                $latestAsset = Asset::withTrashed()
                                    ->whereRaw('barcode REGEXP "^[0-9]+$"')
                                    ->orderByRaw('CAST(barcode AS UNSIGNED) DESC')
                                    ->first();
                if ($latestAsset && is_numeric($latestAsset->barcode)) {
                    $sequence = (int) $latestAsset->barcode + 1;
                }
            }

            // Fetch all existing numeric barcodes once to avoid hitting DB in a loop.
            $existingBarcodes = Asset::withTrashed()
                                    ->whereRaw('barcode REGEXP "^[0-9]+$"')
                                    ->pluck('barcode')
                                    ->flip()
                                    ->toArray();

            $generated = [];
            while (count($generated) < $qty) {
                $candidate = sprintf('%06d', $sequence);
                if (!isset($existingBarcodes[$candidate])) {
                    $generated[] = $candidate;
                }
                $sequence++;
            }

            DB::table('barcode_sequences')
                ->where('name', self::SEQUENCE_NAME)
                ->update(['current_value' => $sequence - 1]);

            return $generated;
        });
    }
}
