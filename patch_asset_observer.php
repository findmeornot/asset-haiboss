<?php
$file = 'app/Observers/AssetObserver.php';
$content = file_get_contents($file);

$insert = <<<PHP

    public function updating(Asset \$asset): void
    {
        if (\$asset->isDirty('classification_id') || \$asset->isDirty('category_id')) {
            \$classification = \App\Models\Classification::find(\$asset->classification_id);
            \$category = \App\Models\Category::find(\$asset->category_id);
            if (\$classification && \$category) {
                \$asset->inventory_number = \App\Services\InventoryNumberGenerator::generate(\$classification, \$category);
            }
        }
    }
PHP;

$content = str_replace("public function created(Asset \$asset): void", ltrim(\$insert) . "\n\n    public function created(Asset \$asset): void", \$content);
file_put_contents($file, \$content);
