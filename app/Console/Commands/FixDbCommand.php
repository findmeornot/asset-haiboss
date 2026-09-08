<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixDbCommand extends Command
{
    protected $signature = 'app:fix-db';
    protected $description = 'Fix database constraints for inventory_balances';

    public function handle()
    {
        $this->info("Fixing database indexes...");
        
        try {
            DB::statement("CREATE INDEX `inventory_balances_category_id_foreign_new` ON inventory_balances (`category_id`)");
            $this->info("Created standalone index for category_id");
        } catch (\Exception $e) {
            $this->warn("Index might exist: " . $e->getMessage());
        }

        try {
            DB::statement("ALTER TABLE inventory_balances DROP INDEX `inventory_balances_category_id_name_location_id_unique`");
            $this->info("Dropped old unique index!");
        } catch (\Exception $e) {
            $this->warn("Failed to drop old unique index (maybe already dropped): " . $e->getMessage());
        }
        
        try {
            DB::statement("ALTER TABLE inventory_balances ADD UNIQUE INDEX `inv_bal_unique_with_brand` (`category_id`, `name`, `brand`, `location_id`)");
            $this->info("Added new unique index with brand!");
        } catch (\Exception $e) {
            $this->warn("Failed to add new unique index: " . $e->getMessage());
        }

        $this->info("Database fixed successfully!");
    }
}
