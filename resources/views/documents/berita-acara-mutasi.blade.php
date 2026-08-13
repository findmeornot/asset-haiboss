<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Berita Acara Mutasi Aset - {{ $asset->inventory_number }}</title>
    <style>
        body { font-family: 'Times New Roman', Times, serif; margin: 40px; line-height: 1.6; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 20px; margin-bottom: 30px; }
        .title { font-size: 18px; font-weight: bold; text-decoration: underline; text-align: center; margin-bottom: 20px; }
        .content { margin-bottom: 40px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 20px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        .signature-area { display: flex; justify-content: space-between; margin-top: 50px; }
        .signature-box { text-align: center; width: 30%; }
        .signature-line { margin-top: 80px; border-top: 1px solid #000; }
        @media print {
            body { margin: 0; padding: 20px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()">Print Dokumen</button>
    </div>

    <div class="header">
        <h2>PT. HAIBOSS ASSET MANAGEMENT</h2>
        <p>Jl. Contoh Alamat No. 123, Kota, Indonesia</p>
    </div>

    <div class="title">
        BERITA ACARA MUTASI / PERPINDAHAN ASET
    </div>

    <div class="content">
        <p>Pada hari ini, tanggal <strong>{{ \Carbon\Carbon::parse($date)->translatedFormat('d F Y') }}</strong>, telah dilakukan mutasi/perpindahan aset dengan rincian sebagai berikut:</p>

        <table>
            <tr>
                <th width="30%">Nomor Inventaris</th>
                <td>{{ $asset->inventory_number }}</td>
            </tr>
            <tr>
                <th>Nama Aset</th>
                <td>{{ $asset->name }}</td>
            </tr>
            <tr>
                <th>Alasan Mutasi</th>
                <td>{{ $movement->reason }}</td>
            </tr>
        </table>

        <h3>Detail Perpindahan</h3>
        <table>
            <tr>
                <th width="30%">Keterangan</th>
                <th width="35%">Dari (Asal)</th>
                <th width="35%">Ke (Tujuan)</th>
            </tr>
            <tr>
                <th>Gedung</th>
                <td>{{ $sourceCampus->name ?? '-' }}</td>
                <td>{{ $destinationCampus->name ?? '-' }}</td>
            </tr>
            <tr>
                <th>Lokasi</th>
                <td>{{ $sourceLocation->name ?? '-' }}</td>
                <td>{{ $destinationLocation->name ?? '-' }}</td>
            </tr>
            <tr>
                <th>Penanggung Jawab (PIC)</th>
                <td>{{ $sourcePic->name ?? '-' }}</td>
                <td>{{ $destinationPic->name ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="signature-area">
        <div class="signature-box">
            <p>Yang Menyerahkan,</p>
            <div class="signature-line">
                {{ $sourcePic->name ?? '...........................' }}
            </div>
        </div>
        <div class="signature-box">
            <p>Disetujui Oleh,</p>
            <div class="signature-line">
                {{ $approvedBy->name ?? '...........................' }}
            </div>
        </div>
        <div class="signature-box">
            <p>Yang Menerima,</p>
            <div class="signature-line">
                {{ $destinationPic->name ?? '...........................' }}
            </div>
        </div>
    </div>
</body>
</html>
