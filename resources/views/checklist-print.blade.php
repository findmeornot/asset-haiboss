<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Checklist</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; color: #000; }
        h1 { font-size: 14px; margin: 0 0 4px; }
        .subtitle { font-size: 9px; color: #444; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 9px; }
        th, td { border: 1px solid #000; padding: 3px 5px; text-align: left; vertical-align: middle; }
        th { background: #eee; }
        .col-no { width: 24px; text-align: center; }
        .col-check { width: 28px; text-align: center; }
        .check-box { display: inline-block; width: 12px; height: 12px; border: 1px solid #000; }
        .col-note { width: 100px; }
        @page { size: A4; margin: 15mm; }
        @media print { body { padding: 0; } }
    </style>
</head>
<body onload="window.print()">
    <h1>Lembar Checklist Barang</h1>
    <div class="subtitle">
        Dicetak: {{ now()->format('d/m/Y H:i') }}
        @if($assets->isNotEmpty() && $assets->first()->printLocationLabel())
            &mdash; {{ $assets->first()->printLocationLabel() }}
        @endif
        &mdash; Total: {{ $assets->count() }} barang
    </div>

    <table>
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th>Nama Barang</th>
                <th>Kode Inventaris</th>
                <th>Kategori</th>
                <th>PIC</th>
                <th class="col-check">Ada</th>
                <th class="col-note">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($assets as $index => $asset)
            <tr>
                <td class="col-no">{{ $index + 1 }}</td>
                <td>{{ $asset->name }}</td>
                <td>{{ $asset->inventory_number }}</td>
                <td>{{ $asset->category?->name }}</td>
                <td>{{ $asset->pic?->name }}</td>
                <td class="col-check"><span class="check-box"></span></td>
                <td class="col-note"></td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
