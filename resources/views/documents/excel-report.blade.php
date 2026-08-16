<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
    <table border="0">
        <tr>
            <th colspan="17" style="font-size: 16px; font-weight: bold; text-align: center;">PT. HAIBOSS ASSET MANAGEMENT</th>
        </tr>
        <tr>
            <th colspan="17" style="font-size: 14px; text-align: center;">Laporan Aset - {{ ucfirst(str_replace('_', ' ', $type)) }}</th>
        </tr>
        <tr>
            <th colspan="17" style="text-align: center;">Tanggal Cetak: {{ $printedAt->format('d/m/Y H:i') }}</th>
        </tr>
        <tr><td colspan="17"></td></tr>
    </table>

    <table border="1">
        <thead>
            <tr>
                <th style="background-color: #d9d9d9;">No</th>

                @if(in_array($type, ['movements']))
                    <th style="background-color: #d9d9d9;">Tgl Mutasi</th>
                    <th style="background-color: #d9d9d9;">Nama Aset</th>
                    <th style="background-color: #d9d9d9;">Dari Gedung</th>
                    <th style="background-color: #d9d9d9;">Ke Gedung</th>
                    <th style="background-color: #d9d9d9;">Pemohon</th>
                    <th style="background-color: #d9d9d9;">Status</th>
                @elseif($type === 'stock_opname')
                    <th style="background-color: #d9d9d9;">Nama Kegiatan</th>
                    <th style="background-color: #d9d9d9;">Gedung</th>
                    <th style="background-color: #d9d9d9;">Tgl Mulai</th>
                    <th style="background-color: #d9d9d9;">Status</th>
                    <th style="background-color: #d9d9d9;">Total Item</th>
                @else
                    {{-- 17 kolom laporan aset sesuai requirement Kak Qory --}}
                    <th style="background-color: #d9d9d9;">Kode</th>
                    <th style="background-color: #d9d9d9;">Kategori Akuntansi</th>
                    <th style="background-color: #d9d9d9;">Kategori</th>
                    <th style="background-color: #d9d9d9;">Nama Barang</th>
                    <th style="background-color: #d9d9d9;">Merk/Tipe</th>
                    <th style="background-color: #d9d9d9;">Nomor Seri</th>
                    <th style="background-color: #d9d9d9;">Jumlah</th>
                    <th style="background-color: #d9d9d9;">Satuan</th>
                    <th style="background-color: #d9d9d9;">Tahun Perolehan</th>
                    <th style="background-color: #d9d9d9;">Sumber Dana</th>
                    <th style="background-color: #d9d9d9;">Gedung</th>
                    <th style="background-color: #d9d9d9;">Ruangan</th>
                    <th style="background-color: #d9d9d9;">PIC</th>
                    <th style="background-color: #d9d9d9;">Kondisi</th>
                    <th style="background-color: #d9d9d9;">Harga Perolehan</th>
                    <th style="background-color: #d9d9d9;">Keterangan</th>
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
                        {{--
                            Mapping 17 kolom:
                            1.  No              → index + 1 (sudah di atas)
                            2.  Kode            → inventory_number
                            3.  Kat. Akuntansi  → classification.name
                            4.  Kategori        → category.name
                            5.  Nama Barang     → name
                            6.  Merk/Tipe       → brand
                            7.  Nomor Seri      → serial_number
                            8.  Jumlah          → purchase.quantity
                            9.  Satuan          → unit
                            10. Tahun Perolehan → YEAR(purchase.purchase_date)
                            11. Sumber Dana     → ownership (company=Universitas, grant=Hibah, loan=Pinjaman)
                            12. Gedung          → campus.name  (campus_id = Gedung di sistem)
                            13. Ruangan         → location.name
                            14. PIC             → pic.name
                            15. Kondisi         → status
                            16. Harga Perolehan → purchase.unit_price
                            17. Keterangan      → notes
                        --}}

                        {{-- 2. Kode --}}
                        <td>{{ $row->inventory_number }}</td>

                        {{-- 3. Kategori Akuntansi --}}
                        <td>{{ $row->classification->name ?? '-' }}</td>

                        {{-- 4. Kategori --}}
                        <td>{{ $row->category->name ?? '-' }}</td>

                        {{-- 5. Nama Barang --}}
                        <td>{{ $row->name }}</td>

                        {{-- 6. Merk/Tipe --}}
                        <td>{{ $row->brand ?? '-' }}</td>

                        {{-- 7. Nomor Seri --}}
                        <td>{{ $row->serial_number ?? '-' }}</td>

                        {{-- 8. Jumlah --}}
                        <td>{{ $row->purchase->quantity ?? '-' }}</td>

                        {{-- 9. Satuan --}}
                        <td>{{ $row->unit ?? '-' }}</td>

                        {{-- 10. Tahun Perolehan (ambil tahun dari purchase_date) --}}
                        <td>{{ $row->purchase?->purchase_date ? \Carbon\Carbon::parse($row->purchase->purchase_date)->format('Y') : '-' }}</td>

                        {{-- 11. Sumber Dana (value internal dipertahankan, label disesuaikan) --}}
                        <td>{{ match($row->ownership) {
                            'company' => 'Yayasan',
                            'grant'   => 'Hibah',
                            'loan'    => 'Pinjaman',
                            default   => $row->ownership ?? '-',
                        } }}</td>

                        {{-- 12. Wilayah → campus.name (campus_id adalah Gedung di sistem) --}}
                        <td>{{ $row->campus->name ?? '-' }}</td>

                        {{-- 13. Ruangan --}}
                        <td>{{ $row->location->name ?? '-' }}</td>

                        {{-- 14. PIC --}}
                        <td>{{ $row->pic->name ?? '-' }}</td>

                        {{-- 15. Kondisi --}}
                        <td>{{ match($row->status) {
                            'stock'                    => 'Stok Tersedia',
                            'active'                   => 'Aktif / Digunakan',
                            'borrowed'                 => 'Dipinjam',
                            'maintenance'              => 'Dalam Perbaikan',
                            'minor_damage'             => 'Rusak Ringan',
                            'major_damage'             => 'Rusak Berat',
                            'lost'                     => 'Hilang',
                            'sold'                     => 'Terjual',
                            'administratively_deleted' => 'Penghapusan Administratif',
                            'destroyed'                => 'Dimusnahkan',
                            default                    => $row->status ?? '-',
                        } }}</td>

                        {{-- 16. Harga Perolehan --}}
                        @if($canViewFinancial)
                            <td>{{ $row->purchase?->unit_price ? number_format($row->purchase->unit_price, 0, ',', '.') : '-' }}</td>
                        @else
                            <td>-</td>
                        @endif

                        {{-- 17. Keterangan --}}
                        <td>{{ $row->notes ?? '-' }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    <table border="0">
        <tr><td colspan="17"></td></tr>
        <tr><td colspan="17"></td></tr>
        <tr>
            <td></td>
            <td colspan="4" style="text-align: center;">Disetujui Oleh,<br><br><br><br><br><b>( .................................... )</b><br>Atasan</td>
            <td colspan="4"></td>
            <td colspan="4" style="text-align: center;">Disusun Oleh,<br><br><br><br><br><b>( {{ $printedBy }} )</b><br>Penyusun Laporan</td>
        </tr>
    </table>
</body>
</html>
