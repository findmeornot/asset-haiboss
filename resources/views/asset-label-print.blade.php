<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Label - {{ $asset->inventory_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
        }
        .label-container {
            border: 2px solid #000;
            padding: 15px;
            width: 250px;
            text-align: center;
        }
        .company-name {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 10px;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
        }
        .asset-name {
            font-size: 14px;
            margin-bottom: 10px;
            word-wrap: break-word;
        }
        .barcode {
            margin: 0 auto;
            width: 100%;
            height: 60px;
            display: flex;
            justify-content: center;
        }
        .barcode svg {
            max-width: 100%;
            height: 100%;
        }
        .inventory-number {
            margin-top: 10px;
            font-family: monospace;
            font-size: 14px;
            font-weight: bold;
        }
        @media print {
            body { padding: 0; }
            @page { margin: 0; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="label-container">
        <div class="company-name">PT. HAIBOSS</div>
        <div class="asset-name">{{ $asset->name }}</div>
        <!-- External API Barcode replaced with SVG -->
        @php
            $barcodeGenerator = new \Picqer\Barcode\BarcodeGeneratorSVG();
        @endphp
        <div class="barcode">
            {!! $barcodeGenerator->getBarcode($asset->barcode, $barcodeGenerator::TYPE_CODE_128, 2, 60) !!}
        </div>
        <div class="inventory-number">{{ $asset->barcode }}</div>
    </div>
</body>
</html>
