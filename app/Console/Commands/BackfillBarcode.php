<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Asset;
use App\Services\BarcodeNumberGenerator;

class BackfillBarcode extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'asset:backfill-barcode';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and backfill barcode for existing assets that do not have one.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $assets = Asset::whereNull('barcode')->get();
        $count = 0;

        foreach ($assets as $asset) {
            $asset->barcode = BarcodeNumberGenerator::generate();
            $asset->saveQuietly();
            $count++;
        }

        $this->info("Successfully backfilled {$count} assets with permanent barcodes.");
    }
}
