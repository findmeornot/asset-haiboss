<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Label - {{ $balance->name }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .labels-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, 250px);
            gap: 15px;
            justify-content: center;
        }
        .label-container { border: 2px solid #000; padding: 15px; width: 220px; text-align: center; background: #fff; page-break-inside: avoid; }
        .company-name { font-weight: bold; font-size: 16px; margin-bottom: 10px; border-bottom: 1px solid #000; padding-bottom: 5px; }
        .asset-name { font-size: 14px; margin-bottom: 10px; word-wrap: break-word; line-height: 1.2; }
        .barcode { margin: 0 auto; width: 100%; height: 50px; display: flex; justify-content: center; overflow: hidden; }
        .barcode svg { max-width: 100%; height: 100%; }
        .inventory-number { margin-top: 8px; font-family: monospace; font-size: 12px; font-weight: bold; }
        .master-badge { font-size: 10px; background: #eee; padding: 2px 5px; border-radius: 3px; display: inline-block; margin-bottom: 5px; }
        @media print { body { padding: 0; } @page { margin: 0; } }
    </style>
</head>
<body onload="window.print()">
    <div class="labels-grid">
        @php
            $barcodeGenerator = new \Picqer\Barcode\BarcodeGeneratorSVG();
            // Get available units
            $units = $balance->units()->where('status', 'available')->get();
        @endphp
        
        @if($units->isEmpty())
            <div style="text-align: center; width: 100%; padding: 50px;">
                <h3>Tidak ada stok tersedia untuk dicetak.</h3>
            </div>
        @else
            @foreach($units as $unit)
            <div class="label-container">
                <div class="company-name">PT. HAIBOSS</div>
                <div class="master-badge">PERSEDIAAN</div>
                <div class="asset-name">{{ $balance->name }}</div>
                <div class="barcode">
                    {!! $barcodeGenerator->getBarcode($unit->sub_barcode, $barcodeGenerator::TYPE_CODE_128, 2, 50) !!}
                </div>
                <div class="inventory-number">{{ $unit->sub_barcode }}</div>
            </div>
            @endforeach
        @endif
    </div>
</body>
</html>
