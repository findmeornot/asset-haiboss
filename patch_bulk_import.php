<?php
$file = 'app/Services/AssetImportService.php';
$content = file_get_contents($file);

$oldLogic = <<<EOD
            // Create N individual Asset records — 1 record = 1 physical unit
            for (\$i = 0; \$i < \$jumlah; \$i++) {
                // Each unit gets its own unique inventory_number (SKU)
                // Kode selalu di-generate otomatis oleh sistem (tidak pakai dari file)
                \$inventoryNumber = InventoryNumberGenerator::generate(\$classification, \$category);

                \$assetData = \$baseAssetData;
                \$assetData['inventory_number'] = \$inventoryNumber;
                \$assetData['purchase_item_id'] = \$purchaseItem->id;
                // Barcode generated internally via observer (AssetObserver::creating)
                // For manual testing/debug we could force it, but best left to Observer

                Asset::create(\$assetData);
                \$count++;
            }
EOD;

$newLogic = <<<EOD
            // Create N individual Asset records in BULK — 1 record = 1 physical unit
            \$inventoryNumbers = InventoryNumberGenerator::generateBulk(\$classification, \$category, \$jumlah);
            
            \$assetRecords = [];
            \$now = now()->toDateTimeString();
            
            foreach (\$inventoryNumbers as \$inventoryNumber) {
                // We generate the barcode mathematically here to avoid Observer N+1 queries.
                // The AssetObserver might still run on create, but since we are doing bulk insert, 
                // Model Events (like Observer::creating) DO NOT RUN on Asset::insert()!
                // This is EXACTLY what we want for performance, but we must manually set ULID & barcode.
                
                \$ulid = (string) \Illuminate\Support\Str::ulid();
                
                // Asset barcode format in observer: \$model->inventory_number . '-' . strtoupper(Str::random(4))
                \$barcode = \$inventoryNumber . '-' . strtoupper(\Illuminate\Support\Str::random(4));
                
                \$assetData = \$baseAssetData;
                \$assetData['id']               = \$ulid; // Assign ULID manually
                \$assetData['inventory_number'] = \$inventoryNumber;
                \$assetData['purchase_item_id'] = \$purchaseItem->id;
                \$assetData['barcode']          = \$barcode;
                \$assetData['created_at']       = \$now;
                \$assetData['updated_at']       = \$now;
                
                \$assetRecords[] = \$assetData;
                \$count++;
            }

            // Perform BULK insert (1 query instead of 1000)
            Asset::insert(\$assetRecords);
EOD;

$content = str_replace($oldLogic, $newLogic, $content);
file_put_contents($file, $content);
echo "Patched AssetImportService for bulk insert\n";
