<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bulk Print Barcode & Checklist - {{ $location->name }}</title>
    <style>
        @page {
            margin: 1cm;
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
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        .label-card {
            border: 1px solid #000;
            padding: 10px;
            box-sizing: border-box;
            border-radius: 4px;
            page-break-inside: avoid;
            background: #fff;
        }
        .label-header {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 8px;
            border-bottom: 1px solid #000;
            padding-bottom: 4px;
        }
        .label-barcode-img {
            text-align: center;
            margin-bottom: 5px;
        }
        .label-barcode-img img {
            max-width: 100%;
            height: 40px;
        }
        .label-barcode-number {
            text-align: center;
            font-family: monospace;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .label-details {
            font-size: 11px;
            line-height: 1.4;
        }
        .label-row {
            display: flex;
        }
        .label-label {
            width: 40px;
            font-weight: bold;
            flex-shrink: 0;
        }
        .label-value {
            flex: 1;
            word-break: break-word;
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
        }
    </style>
</head>
<body onload="window.print()">
    <!-- Document 1: Barcode Labels -->
    <div class="labels-section">
        <h2>Label Barcode - Ruangan: {{ $location->name }}</h2>
        <p class="subtitle">{{ $location->campus->name }}</p>
        
        <div class="labels-grid">
            @foreach($assets as $asset)
            <div class="label-card">
                <div class="label-header">HAIBOSS</div>
                
                <div class="label-barcode-img">
                    <img src="https://bwipjs-api.metafloor.com/?bcid=code128&text={{ urlencode($asset->barcode) }}&scale=2&height=10&includetext=false" alt="Barcode">
                </div>
                <div class="label-barcode-number">{{ $asset->barcode }}</div>
                
                <div class="label-details">
                    <div class="label-row"><span class="label-label">SKU</span> <span class="label-value">: {{ $asset->inventory_number }}</span></div>
                    <div class="label-row"><span class="label-label">Nama</span> <span class="label-value">: {{ $asset->name }}</span></div>
                    @if($asset->brand)
                    <div class="label-row"><span class="label-label">Tipe</span> <span class="label-value">: {{ $asset->brand }}</span></div>
                    @endif
                    @if($asset->serial_number)
                    <div class="label-row"><span class="label-label">SN</span> <span class="label-value">: {{ $asset->serial_number }}</span></div>
                    @endif
                    @if($asset->pic)
                    <div class="label-row"><span class="label-label">PIC</span> <span class="label-value">: {{ $asset->pic->name }}</span></div>
                    @endif
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
