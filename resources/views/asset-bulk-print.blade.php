<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bulk Print Barcode & Checklist - {{ $location->name }}</title>
    <style>
        @page {
            size: A3 portrait;
            margin: 10mm;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            font-size: 12px;
            color: #000;
        }
        .page-break { page-break-before: always; }

        /* Barcode Grid */
        .labels-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, 50mm);
            gap: 2mm;
            justify-content: center;
        }
        .label-card {
            width: 50mm;
            height: 25mm;
            border: 1px solid #000;
            box-sizing: border-box;
            page-break-inside: avoid;
            background: #fff;
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
            padding: 0 4mm; /* Space for sideways watermark */
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
            padding: 0.5mm 1mm; /* extra space left and right */
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

        /* Checklist Table */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 11px;
        }
        th, td {
            border: 1px solid #000;
            padding: 6px;
            text-align: left;
            page-break-inside: avoid;
        }
        th {
            background-color: #f0f0f0;
            -webkit-print-color-adjust: exact;
            color-adjust: exact;
        }
        .checkbox-box {
            width: 16px;
            height: 16px;
            border: 1px solid #000;
            margin: 0 auto;
        }
        .text-center { text-align: center; }
        h2 { text-align: center; margin-bottom: 15px; font-size: 18px; }
        p.subtitle { text-align: center; margin-top: -10px; margin-bottom: 20px; font-size: 14px; }

        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
        }
    </style>
</head>
<body onload="window.print()">
    <!-- Document 1: Barcode Labels -->
    <div class="labels-section">
        <h2 class="no-print">Label Barcode - Ruangan: {{ $location->name }}</h2>
        <p class="subtitle no-print">{{ $location->campus->name }}</p>

        <div class="labels-grid">
            @php
                $barcodeGenerator = new \Picqer\Barcode\BarcodeGeneratorSVG();
            @endphp
            @foreach($assets as $asset)
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
            @endforeach
        </div>
    </div>

    <!-- Document 2: Checklist Barang -->
    <div class="page-break"></div>
    <div class="checklist-section">
        <h2>Checklist Barang - Ruangan: {{ $location->name }}</h2>
        <p class="subtitle">{{ $location->campus->name }} &bull; Total: {{ $assets->count() }} barang</p>

        <table>
            <thead>
                <tr>
                    <th width="4%">No</th>
                    <th width="20%">Nama Barang</th>
                    <th width="15%">Merk/Tipe</th>
                    <th width="15%">Nomor Seri</th>
                    <th width="15%">PIC</th>
                    <th width="13%">SKU</th>
                    <th width="10%">Barcode</th>
                    <th width="8%" class="text-center">Checklist</th>
                </tr>
            </thead>
            <tbody>
                @foreach($assets as $index => $asset)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $asset->name }}</td>
                    <td>{{ $asset->brand ?? '-' }}</td>
                    <td>{{ $asset->serial_number ?? '-' }}</td>
                    <td>{{ $asset->pic ? $asset->pic->name : '-' }}</td>
                    <td>{{ $asset->inventory_number }}</td>
                    <td>{{ $asset->barcode }}</td>
                    <td class="text-center"><div class="checkbox-box"></div></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
