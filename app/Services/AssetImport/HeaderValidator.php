<?php

namespace App\Services\AssetImport;

/**
 * Validates the shape of the uploaded file: required headers present,
 * no duplicates, and known-wrong-header typo suggestions.
 */
class HeaderValidator
{
    /**
     * Header wajib yang harus ada di file, dalam urutan resmi requirement.
     */
    public const REQUIRED_HEADERS = [
        'No',
        'Kode',
        'Kategori Akuntansi',
        'Kategori',
        'Nama Barang',
        'Merk/Tipe',
        'Nomor Seri',
        'Jumlah',
        'Satuan',
        'Tahun Perolehan',
        'Sumber Dana',
        'Gedung',
        'Ruangan',
        'PIC',
        'Status',
        'Kondisi',
        'Harga Perolehan',
        'Keterangan',
    ];

    /**
     * Header yang sering salah digunakan, beserta koreksinya.
     */
    private const KNOWN_WRONG_HEADERS = [
        'Wilayah'               => 'Gedung',
        'Jmlh'                  => 'Jumlah',
        'Lokasi'                => 'Ruangan',
        'Lokasi Detail'         => 'Ruangan',
        'Ruang'                 => 'Ruangan',
        'Gedung (Kampus)'       => 'Gedung',
        'Kepemilikan'           => 'Sumber Dana',
        'Status Barang'         => 'Kondisi',
        'Nomor Inventaris'      => 'Kode',
        'Inventory Number'      => 'Kode',
        'Merk'                  => 'Merk/Tipe',
        'Tipe'                  => 'Merk/Tipe',
        'Brand'                 => 'Merk/Tipe',
        'Sumber Dana (Univ/Hibah)' => 'Sumber Dana',
        'Klasifikasi Barang'    => 'Kategori Akuntansi',
        'Klasifikasi'           => 'Kategori Akuntansi',
        'Kategori Barang'       => 'Kategori',
        'Harga'                 => 'Harga Perolehan',
        'Harga Unit'            => 'Harga Perolehan',
        'Tahun'                 => 'Tahun Perolehan',
        'Notes'                 => 'Keterangan',
        'Catatan'               => 'Keterangan',
    ];

    /**
     * Validasi header file. Return array of error strings (kosong = valid).
     */
    public function validate(array $headers): array
    {
        $errors             = [];
        $headerLower        = array_map('mb_strtolower', $headers);
        $alreadyReportedWrong = []; // Track wrong headers yang sudah dilaporkan

        // Cek duplicate header
        $duplicates = array_keys(array_filter(array_count_values($headers), fn($c) => $c > 1));
        foreach ($duplicates as $dup) {
            $errors[] = "Header duplikat ditemukan: \"{$dup}\".";
        }

        // Cek setiap required header
        $missing = [];
        foreach (self::REQUIRED_HEADERS as $required) {
            if (!in_array(mb_strtolower($required), $headerLower)) {
                // Cek apakah user pakai wrong header
                $wrongSuggestion = null;
                foreach (self::KNOWN_WRONG_HEADERS as $wrong => $correct) {
                    if ($correct === $required && in_array(mb_strtolower($wrong), $headerLower)) {
                        $wrongSuggestion = $wrong;
                        break;
                    }
                }

                if ($wrongSuggestion) {
                    $errors[] = "Header \"{$wrongSuggestion}\" tidak dikenali. Gunakan \"{$required}\".";
                    $alreadyReportedWrong[mb_strtolower($wrongSuggestion)] = true;
                } else {
                    $missing[] = $required;
                }
            }
        }

        if (!empty($missing)) {
            $errors[] = "Header berikut tidak ditemukan: " . implode(', ', array_map(fn($h) => "\"{$h}\"", $missing)) . ".";
        }

        // Cek header asing yang mungkin salah ketik (belum dilaporkan di atas)
        foreach ($headers as $h) {
            $hLower = mb_strtolower($h);
            if (
                !in_array($hLower, array_map('mb_strtolower', self::REQUIRED_HEADERS))
                && isset(self::KNOWN_WRONG_HEADERS[$h])
                && !isset($alreadyReportedWrong[$hLower])
            ) {
                $errors[] = "Header \"{$h}\" tidak dikenali. Gunakan \"" . self::KNOWN_WRONG_HEADERS[$h] . "\".";
            }
        }

        return array_values(array_unique($errors));
    }
}
