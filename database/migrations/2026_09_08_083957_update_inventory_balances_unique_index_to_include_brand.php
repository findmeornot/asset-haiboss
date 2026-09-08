<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Standalone index for category_id (so we can safely drop the unique index that MySQL might use for FKs)
        try {
            DB::statement("CREATE INDEX `inventory_balances_category_id_foreign_new` ON inventory_balances (`category_id`)");
        } catch (\Exception $e) {}

        // Drop old index
        try {
            DB::statement("ALTER TABLE inventory_balances DROP INDEX `inventory_balances_category_id_name_location_id_unique`");
        } catch (\Exception $e) {}
        
        // Add new index
        try {
            DB::statement("ALTER TABLE inventory_balances ADD UNIQUE INDEX `inv_bal_unique_with_brand` (`category_id`, `name`, `brand`, `location_id`)");
        } catch (\Exception $e) {}
    }

    public function down(): void
    {
        // No safe down needed
    }
};
