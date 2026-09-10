<?php
$file = 'app/Services/InventoryNumberGenerator.php';
$content = file_get_contents($file);

$newMethod = <<<EOD
    /**
     * Generate an array of unique inventory numbers in bulk for performance.
     */
    public static function generateBulk(?Classification \$classification = null, ?Category \$category = null, int \$qty = 1): array
    {
        if (\$qty <= 0) return [];

        return DB::transaction(function () use (\$classification, \$category, \$qty) {
            \$classCode = self::getClassCode(\$classification ? \$classification->name : 'NOCLASS');
            \$catCode   = self::getCatCode(\$category ? \$category->name : 'NOCAT');
            \$prefix = "INV/{\$classCode}/{\$catCode}";

            DB::table('inventory_number_sequences')->upsert(
                ['name' => \$prefix, 'current_value' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['name'], ['updated_at']
            );

            \$seqRow = DB::table('inventory_number_sequences')->where('name', \$prefix)->lockForUpdate()->first();
            \$sequence = \$seqRow->current_value + 1;

            if (\$sequence === 1) {
                \$latestAsset = Asset::where('inventory_number', 'like', "{\$prefix}/%")
                                    ->orderByRaw('LENGTH(inventory_number) DESC')
                                    ->orderBy('inventory_number', 'desc')
                                    ->first();
                if (\$latestAsset) {
                    \$parts = explode('/', \$latestAsset->inventory_number);
                    \$lastPart = end(\$parts);
                    if (is_numeric(\$lastPart)) {
                        \$sequence = (int) \$lastPart + 1;
                    }
                }
            }

            // Fetch all existing sequences for this prefix to avoid hitting DB in a loop
            \$existingNumbers = Asset::where('inventory_number', 'like', "{\$prefix}/%")
                                    ->pluck('inventory_number')
                                    ->flip()
                                    ->toArray();

            \$generated = [];
            while (count(\$generated) < \$qty) {
                \$candidate = sprintf('%s/%04d', \$prefix, \$sequence);
                if (!isset(\$existingNumbers[\$candidate])) {
                    \$generated[] = \$candidate;
                }
                \$sequence++;
            }

            // Update sequence table to the last checked sequence
            DB::table('inventory_number_sequences')
                ->where('name', \$prefix)
                ->update(['current_value' => \$sequence - 1]);

            return \$generated;
        });
    }

EOD;

// Insert new method right before getClassCode
$content = str_replace(
    "    private static function getClassCode(string \$name): string",
    $newMethod . "    private static function getClassCode(string \$name): string",
    $content
);

file_put_contents($file, $content);
echo "Patched InventoryNumberGenerator\n";
