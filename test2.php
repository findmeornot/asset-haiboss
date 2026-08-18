<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$schema = \Illuminate\Support\Facades\Schema::getConnection();
$cols = $schema->getSchemaBuilder()->getColumns('assets');
$barcode = array_filter($cols, fn($c) => $c['name'] === 'barcode');
echo json_encode(array_values($barcode)) . "\n";
$indexes = $schema->getSchemaBuilder()->getIndexes('assets');
$unique = array_filter($indexes, fn($i) => $i['unique'] && in_array('barcode', $i['columns']));
echo "Unique count: " . count($unique) . "\n";
echo "Total assets: " . \App\Models\Asset::withTrashed()->count() . "\n";
echo "Null barcodes: " . \App\Models\Asset::withTrashed()->whereNull('barcode')->count() . "\n";
