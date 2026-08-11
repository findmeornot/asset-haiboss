<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
    <table border="0">
        <tr>
            <th colspan="7" style="font-size: 16px; font-weight: bold; text-align: center;">PT. HAIBOSS ASSET MANAGEMENT</th>
        </tr>
        <tr>
            <th colspan="7" style="font-size: 14px; text-align: center;">Laporan Aset - {{ ucfirst(str_replace('_', ' ', $type)) }}</th>
        </tr>
        <tr>
            <th colspan="7" style="text-align: center;">Tanggal Cetak: {{ $printedAt->format('d/m/Y H:i') }}</th>
        </tr>
        <tr><td colspan="7"></td></tr>
    </table>

    <table border="1">
        <thead>
            <tr>
                <th style="background-color: #d9d9d9;">No</th>
                
                @if(in_array($type, ['movements']))
                    <th style="background-color: #d9d9d9;">Tgl Mutasi</th>
                    <th style="background-color: #d9d9d9;">Nama Aset</th>
                    <th style="background-color: #d9d9d9;">Dari Kampus</th>
                    <th style="background-color: #d9d9d9;">Ke Kampus</th>
                    <th style="background-color: #d9d9d9;">Pemohon</th>
                    <th style="background-color: #d9d9d9;">Status</th>
                @elseif($type === 'stock_opname')
                    <th style="background-color: #d9d9d9;">Nama Kegiatan</th>
                    <th style="background-color: #d9d9d9;">Kampus</th>
                    <th style="background-color: #d9d9d9;">Tgl Mulai</th>
                    <th style="background-color: #d9d9d9;">Status</th>
                    <th style="background-color: #d9d9d9;">Total Item</th>
                @else
                    <th style="background-color: #d9d9d9;">Nomor Inventaris</th>
                    <th style="background-color: #d9d9d9;">Nama Barang</th>
                    <th style="background-color: #d9d9d9;">Kategori</th>
                    <th style="background-color: #d9d9d9;">Kampus</th>
                    <th style="background-color: #d9d9d9;">Lokasi</th>
                    <th style="background-color: #d9d9d9;">Status</th>
                    <th style="background-color: #d9d9d9;">PIC</th>
                    @if($canViewFinancial)
                        <th style="background-color: #d9d9d9;">Tgl Beli</th>
                        <th style="background-color: #d9d9d9;">Harga Unit</th>
                        <th style="background-color: #d9d9d9;">Total Harga</th>
                    @endif
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($data as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    
                    @if(in_array($type, ['movements']))
                        <td>{{ $row->movement_date ? $row->movement_date->format('d/m/Y') : '-' }}</td>
                        <td>{{ $row->asset->name ?? '-' }}</td>
                        <td>{{ $row->sourceCampus->name ?? '-' }}</td>
                        <td>{{ $row->destinationCampus->name ?? '-' }}</td>
                        <td>{{ $row->requestedBy->name ?? '-' }}</td>
                        <td>{{ $row->status }}</td>
                    @elseif($type === 'stock_opname')
                        <td>{{ $row->name }}</td>
                        <td>{{ $row->campus->name ?? '-' }}</td>
                        <td>{{ $row->start_date }}</td>
                        <td>{{ $row->status }}</td>
                        <td>{{ $row->items->count() }}</td>
                    @else
                        <td>{{ $row->inventory_number }}</td>
                        <td>{{ $row->name }}</td>
                        <td>{{ $row->category->name ?? '-' }}</td>
                        <td>{{ $row->campus->name ?? '-' }}</td>
                        <td>{{ $row->location->name ?? '-' }}</td>
                        <td>{{ $row->status }}</td>
                        <td>{{ $row->pic->name ?? '-' }}</td>
                        
                        @if($canViewFinancial)
                            <td>{{ $row->purchase->purchase_date ?? '-' }}</td>
                            <td>{{ $row->purchase->unit_price ?? 0 }}</td>
                            <td>{{ $row->purchase->total_price ?? 0 }}</td>
                        @endif
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    <table border="0">
        <tr><td colspan="7"></td></tr>
        <tr><td colspan="7"></td></tr>
        <tr>
            <td></td>
            <td colspan="2" style="text-align: center;">Disetujui Oleh,<br><br><br><br><br><b>( .................................... )</b><br>Atasan</td>
            <td colspan="2"></td>
            <td colspan="2" style="text-align: center;">Disusun Oleh,<br><br><br><br><br><b>( {{ $printedBy }} )</b><br>Penyusun Laporan</td>
        </tr>
    </table>
</body>
</html>
