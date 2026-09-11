<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Label - {{ $asset->inventory_number }}</title>
    <style>
        @page {
            size: 50mm 25mm;
            margin: 0;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #000;
            background: #fff;
            height: 100vh;
        }
        .label-card {
            width: 50mm;
            height: 25mm;
            border: 1px solid #000;
            box-sizing: border-box;
            position: relative;
            overflow: hidden;
            padding: 1mm;
            display: flex;
            flex-direction: column;
        }
        .watermark-left, .watermark-right {
            position: absolute;
            top: 0;
            bottom: 0;
            font-size: 4.5pt;
            font-weight: bold;
            color: #777;
            opacity: 0.35;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1;
            white-space: nowrap;
        }
        .watermark-left {
            left: 1mm;
            writing-mode: vertical-lr;
            transform: rotate(180deg);
        }
        .watermark-right {
            right: 1mm;
            writing-mode: vertical-rl;
        }
        .label-content {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            height: 100%;
            width: 100%;
            justify-content: space-between;
            padding: 0 4mm;
            box-sizing: border-box;
        }
        .label-location {
            font-size: 5.5pt;
            font-weight: bold;
            text-align: center;
            height: 4mm;
            line-height: 4mm;
            text-transform: uppercase;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .label-barcode-img {
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            overflow: hidden;
            padding: 0.5mm 1mm;
        }
        .label-barcode-img svg, .label-barcode-img img {
            height: 100%;
            max-height: 10mm;
            width: auto;
            max-width: 100%;
        }
        .label-sku {
            height: 4.5mm;
            line-height: 4.5mm;
            background: #000;
            color: #fff;
            font-weight: bold;
            text-align: center;
            font-size: 6.5pt;
            letter-spacing: 0.5px;
            box-sizing: border-box;
            border-radius: 1px;
            overflow: hidden;
        }
        @media print {
            body { padding: 0; height: auto; }
            .label-card { border: none; }
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
        }
    </style>
</head>
<body onload="window.print()">
    @php
        $barcodeGenerator = new \Picqer\Barcode\BarcodeGeneratorSVG();
    @endphp
    <div class="label-card">
        <div class="watermark-left">UNIVERSITAS STEKOM</div>
        <div class="watermark-right">UNIVERSITAS STEKOM</div>
        <div class="label-content">
            <div class="label-location">
                {{ $asset->campus->code ?? $asset->campus->name ?? '' }} - {{ $asset->location->name ?? '' }}
            </div>
            <div class="label-barcode-img">
                {!! $barcodeGenerator->getBarcode($asset->inventory_number, $barcodeGenerator::TYPE_CODE_128, 1, 25) !!}
            </div>
            <div class="label-sku">{{ $asset->inventory_number }}</div>
        </div>
    </div>
</body>
</html>
