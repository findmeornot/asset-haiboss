<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Asset;
use App\Models\Classification;
use App\Models\Category;

class InventoryNumberGenerator
{
    /**
     * Generate a unique inventory number for a new asset (e.g. INV/AST/ELK/0001).
     */
    public static function generate(?Classification $classification = null, ?Category $category = null): string
    {
        return DB::transaction(function () use ($classification, $category) {

            $classCode = self::getClassCode($classification ? $classification->name : 'NOCLASS');
            $catCode   = self::getCatCode($category ? $category->name : 'NOCAT');

            $prefix = "INV/{$classCode}/{$catCode}";

            // Use upsert to handle concurrent first inserts safely
            DB::table('inventory_number_sequences')->upsert(
                [
                    'name' => $prefix,
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
                ->where('name', $prefix)
                ->lockForUpdate()
                ->first();

            $sequence = $seqRow->current_value + 1;
            
            // Just in case it's 1 and there are legacy items not tracked in sequence table
            if ($sequence === 1) {
                $latestAsset = Asset::where('inventory_number', 'like', "{$prefix}/%")
                                    ->orderByRaw('LENGTH(inventory_number) DESC')
                                    ->orderBy('inventory_number', 'desc')
                                    ->first();
                if ($latestAsset) {
                    $parts = explode('/', $latestAsset->inventory_number);
                    $lastPart = end($parts);
                    if (is_numeric($lastPart)) {
                        $sequence = (int) $lastPart + 1;
                    }
                }
            }

            DB::table('inventory_number_sequences')
                ->where('name', $prefix)
                ->update(['current_value' => $sequence]);

            $inventoryNumber = sprintf('%s/%04d', $prefix, $sequence);

            // Ensure uniqueness
            while (Asset::where('inventory_number', $inventoryNumber)->exists()) {
                $sequence++;
                DB::table('inventory_number_sequences')
                    ->where('name', $prefix)
                    ->update(['current_value' => $sequence]);
                $inventoryNumber = sprintf('%s/%04d', $prefix, $sequence);
            }

            return $inventoryNumber;
        });
    }

    /**
     * Kode singkat untuk Kategori Akuntansi (classification).
     * Tidak boleh sama dengan prefix 'INV' agar tidak double.
     */
    private static function getClassCode(string $name): string
    {
        $name = strtoupper(trim($name));
        $map = [
            'ASET'             => 'AST',
            'INVENTARIS'       => 'IVT',   // bukan INV agar tidak bentrok dengan prefix
            'PERSEDIAAN BARANG'=> 'PRD',
        ];

        if (isset($map[$name])) {
            return $map[$name];
        }

        // Auto-generate: 3 huruf pertama konsonan
        return self::makeInitial($name);
    }

    /**
     * Kode singkat untuk Kategori (category).
     */
    private static function getCatCode(string $name): string
    {
        $name = strtoupper(trim($name));
        $map = [
            'ELEKTRONIK'       => 'ELK',
            'ELEKTRONIK LAINNYA' => 'ELL',
            'MESIN'            => 'MSN',
            'FURNITURE'        => 'FNR',
            'KENDARAAN'        => 'KND',
            'ATK'              => 'ATK',
            'DEKORASI'         => 'DKR',
            'JARINGAN'         => 'JRN',
            'MAINAN'           => 'MNN',
            'MAKANAN'          => 'MKN',
            'SOUVENIR'         => 'SVN',
        ];

        if (isset($map[$name])) {
            return $map[$name];
        }

        return self::makeInitial($name);
    }

    /**
     * Auto-generate 3-letter initial: huruf pertama + 2 konsonan berikutnya.
     */
    private static function makeInitial(string $name): string
    {
        $name  = strtoupper(trim($name));
        $first = substr($name, 0, 1);
        $rest  = preg_replace('/[AEIOU\s\W]/', '', substr($name, 1));

        return str_pad(substr($first . $rest, 0, 3), 3, 'X');
    }
}
