<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$record = Illuminate\Support\Facades\DB::table('inventory_balances')->latest('id')->first();
if ($record) {
    echo "Photo: " . var_export($record->photo, true) . "\n";
    print_r($record);
} else {
    echo "No records found.\n";
}
