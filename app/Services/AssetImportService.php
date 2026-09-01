<?php

namespace App\Services;

use App\Models\Asset;
use App\Services\SupplyBarcodeGenerator;
use App\Models\InventoryBalance;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Classification;
use App\Models\Employee;
use App\Models\Location;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class AssetImportService
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
     * Mapping dari display value Sumber Dana → internal value.
     * Value lama (company/grant/loan) tetap dipertahankan untuk backward-compat.
     */
    public const OWNERSHIP_MAP = [
        'yayasan'     => 'company',
        'perusahaan'  => 'company',
        'company'     => 'company',
        'hibah'       => 'grant',
        'grant'       => 'grant',
        'pinjaman'    => 'loan',
        'loan'        => 'loan',
    ];

    /**
     * Mapping dari display value Status → internal value (status).
     */
    public const STATUS_MAP = [
        'stok (gudang)'             => 'stock',
        'stok'                      => 'stock',
        'stock'                     => 'stock',
        'aktif / digunakan'         => 'active',
        'aktif'                     => 'active',
        'active'                    => 'active',
        'dipinjam'                  => 'borrowed',
        'borrowed'                  => 'borrowed',
        'dalam perbaikan'           => 'maintenance',
        'perbaikan'                 => 'maintenance',
        'maintenance'               => 'maintenance',
        'hilang'                    => 'lost',
        'lost'                      => 'lost',
        'terjual'                   => 'sold',
        'sold'                      => 'sold',
        'dihapuskan / musnah'       => 'disposed',
        'disposed'                  => 'disposed',
        'penghapusan administratif' => 'administratively_deleted',
        'administratively_deleted'  => 'administratively_deleted',
        'dimusnahkan'               => 'destroyed',
        'destroyed'                 => 'destroyed',
    ];

    /**
     * Mapping dari display value Kondisi → internal value (kondisi).
     */
    public const KONDISI_MAP = [
        'baik'                      => 'good',
        'good'                      => 'good',
        'rusak ringan'              => 'minor_damage',
        'minor_damage'              => 'minor_damage',
        'rusak berat'               => 'major_damage',
        'major_damage'              => 'major_damage',
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
     * Parse file dan return array of row data (associative, keyed by header name).
     * Supports: .csv, .xlsx, .xls
     *
     * @param string      $filePath  Path ke file temporary
     * @param string|null $extension Extension eksplisit (opsional, fallback ke auto-detect dari path)
     *
     * @throws \RuntimeException jika format file tidak didukung
     */
    public function parseFile(string $filePath, ?string $extension = null): array
    {
        $extension = strtolower($extension ?? pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'csv' || $extension === 'txt') {
            return $this->parseCsv($filePath);
        }

        if (in_array($extension, ['xlsx', 'xls'])) {
            return $this->parseSpreadsheet($filePath);
        }

        throw new \RuntimeException("Format file '{$extension}' tidak didukung. Gunakan CSV, XLSX, atau XLS.");
    }

    /**
     * Validasi header file. Return array of error strings (kosong = valid).
     */
    public function validateHeaders(array $headers): array
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


    /**
     * Validasi semua baris data. Return array of errors per baris.
     * Format: [ ['row' => N, 'field' => 'X', 'message' => '...'], ... ]
     *
     * @param array $rows  Raw rows dari parseFile()
     * @param array $headerErrors Jika header sudah ada error, lewati validasi row
     */
    private function cleanString(?string $str): string
    {
        if ($str === null) return '';
        // Hapus karakter whitespace tersembunyi seperti NBSP (\xC2\xA0)
        return trim(preg_replace('/^[\pZ\pC]+|[\pZ\pC]+$/u', '', $str));
    }

    public function validateRows(array $rows): array
    {
        // Pre-load semua master data ke memory untuk efisiensi
        $classifications = Classification::all()->keyBy(fn($c) => mb_strtolower($this->cleanString($c->name)));
        $categories      = Category::all()->keyBy(fn($c) => mb_strtolower($this->cleanString($c->name)));
        $campuses        = Campus::all()->keyBy(fn($c) => mb_strtolower($this->cleanString($c->name)));
        $locations       = Location::with('campus')->get();
        $employees       = Employee::all()->keyBy(fn($e) => mb_strtolower($this->cleanString($e->name)));

        $errors = [];

        foreach ($rows as $rowIndex => $row) {
            $rowNum   = $row['_row_number'] ?? ($rowIndex + 2); // +2 karena baris 1 = header
            $rowErrors = [];

            // ──────────────────────────────────────────
            // 1. Kode — diabaikan, selalu di-generate otomatis oleh sistem
            // ──────────────────────────────────────────

            // ──────────────────────────────────────────
            // 2. Kategori Akuntansi (classification_id) — wajib
            // ──────────────────────────────────────────
            $classificationName = $this->cleanString($row['Kategori Akuntansi'] ?? '');
            $classification     = null;
            if (empty($classificationName)) {
                $rowErrors[] = ['field' => 'Kategori Akuntansi', 'message' => 'Kategori Akuntansi tidak boleh kosong.'];
            } else {
                $classification = $classifications->get(mb_strtolower($classificationName));
                if (!$classification) {
                    $rowErrors[] = ['field' => 'Kategori Akuntansi', 'message' => "Kategori Akuntansi \"{$classificationName}\" tidak ditemukan di master data."];
                }
            }

            // ──────────────────────────────────────────
            // 3. Kategori (category_id) — wajib, jika belum ada akan dibuat otomatis
            // ──────────────────────────────────────────
            $categoryName = trim($row['Kategori'] ?? '');
            $category     = null;
            if (empty($categoryName)) {
                $rowErrors[] = ['field' => 'Kategori', 'message' => 'Kategori tidak boleh kosong.'];
            } else {
                $category = $categories->get(mb_strtolower($categoryName));
                if ($category && $classification) {
                    // Hanya validasi linkage jika kategori sudah ada di sistem
                    $linked = $category->classifications()->whereKey($classification->id)->exists();
                    if (!$linked) {
                        $rowErrors[] = ['field' => 'Kategori', 'message' => "Kategori \"{$categoryName}\" tidak termasuk dalam Kategori Akuntansi \"{$classificationName}\"."];
                    }
                }
                // Jika belum ada sama sekali → akan dibuat otomatis saat import, tidak error
            }

            // ──────────────────────────────────────────
            // 4. Nama Barang — wajib
            // ──────────────────────────────────────────
            $namaBarang = trim($row['Nama Barang'] ?? '');
            if (empty($namaBarang)) {
                $rowErrors[] = ['field' => 'Nama Barang', 'message' => 'Nama Barang tidak boleh kosong.'];
            }

            // ──────────────────────────────────────────
            // 5. Jumlah — wajib, harus angka
            // ──────────────────────────────────────────
            $jumlah     = trim((string) ($row['Jumlah'] ?? ''));
            $jumlahInt  = null;
            if ($jumlah === '') {
                $rowErrors[] = ['field' => 'Jumlah', 'message' => 'Jumlah tidak boleh kosong.'];
            } elseif (!is_numeric($jumlah) || (int) $jumlah <= 0) {
                $rowErrors[] = ['field' => 'Jumlah', 'message' => "Jumlah harus berupa angka positif. Nilai \"{$jumlah}\" tidak valid."];
            } else {
                $jumlahInt = (int) $jumlah;
            }

            // ──────────────────────────────────────────
            // 6. Tahun Perolehan — opsional (kosong / strip = tidak diketahui)
            // ──────────────────────────────────────────
            $tahunRaw = trim((string) ($row['Tahun Perolehan'] ?? ''));
            $tahun    = null;
            // Anggap kosong atau strip/dash sebagai "tidak diketahui"
            $tahunUnknown = $tahunRaw === '' || preg_match('/^-+$/', $tahunRaw);
            if (!$tahunUnknown) {
                if (!preg_match('/^\d{4}$/', $tahunRaw)) {
                    $rowErrors[] = ['field' => 'Tahun Perolehan', 'message' => "Tahun Perolehan harus berupa 4 digit tahun (contoh: 2024). Nilai \"{$tahunRaw}\" tidak valid."];
                } else {
                    $yr = (int) $tahunRaw;
                    if ($yr < 1900 || $yr > (int) date('Y') + 5) {
                        $rowErrors[] = ['field' => 'Tahun Perolehan', 'message' => "Tahun Perolehan \"{$tahunRaw}\" di luar rentang yang wajar."];
                    } else {
                        $tahun = $yr;
                    }
                }
            }

            // ──────────────────────────────────────────
            // 7. Sumber Dana (ownership) — wajib
            // ──────────────────────────────────────────
            $sumberDanaRaw = trim($row['Sumber Dana'] ?? '');
            $ownershipVal  = null;
            if (empty($sumberDanaRaw)) {
                $rowErrors[] = ['field' => 'Sumber Dana', 'message' => 'Sumber Dana tidak boleh kosong.'];
            } else {
                $ownershipVal = self::OWNERSHIP_MAP[mb_strtolower($sumberDanaRaw)] ?? null;
                if (!$ownershipVal) {
                    $validOptions = 'Yayasan, Hibah, Pinjaman';
                    $rowErrors[]  = ['field' => 'Sumber Dana', 'message' => "Sumber Dana \"{$sumberDanaRaw}\" tidak valid. Gunakan: {$validOptions}."];
                }
            }

            // ──────────────────────────────────────────
            // 8. Gedung (campus_id) — wajib
            // ──────────────────────────────────────────
            $gedungName = trim($row['Gedung'] ?? '');
            $campus     = null;
            if (empty($gedungName)) {
                $rowErrors[] = ['field' => 'Gedung', 'message' => 'Gedung tidak boleh kosong.'];
            } else {
                $campus = $campuses->get(mb_strtolower($gedungName));
                if (!$campus) {
                    $rowErrors[] = ['field' => 'Gedung', 'message' => "Gedung \"{$gedungName}\" tidak ditemukan di master data."];
                }
            }

            // ──────────────────────────────────────────
            // 9. Ruangan (location_id) — wajib, jika belum ada akan dibuat otomatis
            // ──────────────────────────────────────────
            $ruanganName = trim($row['Ruangan'] ?? '');
            $location    = null;
            if (empty($ruanganName)) {
                $rowErrors[] = ['field' => 'Ruangan', 'message' => 'Ruangan tidak boleh kosong.'];
            } elseif ($campus) {
                $location = $locations
                    ->where('campus_id', $campus->id)
                    ->first(fn($l) => mb_strtolower($l->name) === mb_strtolower($ruanganName));

                if (!$location) {
                    // Cek apakah ruangan ada tapi di gedung lain (konflik)
                    $locationElsewhere = $locations
                        ->first(fn($l) => mb_strtolower($l->name) === mb_strtolower($ruanganName));

                    if ($locationElsewhere) {
                        $rowErrors[] = ['field' => 'Ruangan', 'message' => "Ruangan \"{$ruanganName}\" tidak berada di Gedung \"{$gedungName}\". Ruangan tersebut berada di Gedung \"{$locationElsewhere->campus->name}\"."];
                    }
                    // Jika belum ada sama sekali → akan dibuat otomatis saat import, tidak error
                }
            }

            // ──────────────────────────────────────────
            // 10. PIC (pic_id) — opsional, jika belum ada akan dibuat otomatis
            // ──────────────────────────────────────────
            $picName = trim($row['PIC'] ?? '');
            // Tidak divalidasi keberadaannya — jika belum ada di sistem akan dibuat otomatis saat import

            // ──────────────────────────────────────────
            // 11. Status (status) — wajib
            // ──────────────────────────────────────────
            $statusRaw = trim($row['Status'] ?? '');
            if (empty($statusRaw)) {
                $rowErrors[] = ['field' => 'Status', 'message' => 'Status tidak boleh kosong.'];
            } else {
                if (!isset(self::STATUS_MAP[mb_strtolower($statusRaw)])) {
                    $validStatus = implode(', ', array_unique(array_keys(self::STATUS_MAP)));
                    $rowErrors[]  = ['field' => 'Status', 'message' => "Status \"{$statusRaw}\" tidak valid. Gunakan salah satu dari: {$validStatus}."];
                }
            }

            // ──────────────────────────────────────────
            // 11b. Kondisi (kondisi) — wajib
            // ──────────────────────────────────────────
            $kondisiRaw = trim($row['Kondisi'] ?? '');
            if (empty($kondisiRaw)) {
                $rowErrors[] = ['field' => 'Kondisi', 'message' => 'Kondisi tidak boleh kosong.'];
            } else {
                if (!isset(self::KONDISI_MAP[mb_strtolower($kondisiRaw)])) {
                    $validKondisi = implode(', ', array_unique(array_keys(self::KONDISI_MAP)));
                    $rowErrors[]  = ['field' => 'Kondisi', 'message' => "Kondisi \"{$kondisiRaw}\" tidak valid. Gunakan salah satu dari: {$validKondisi}."];
                }
            }

            // ──────────────────────────────────────────
            // 12. Harga Perolehan — opsional, kosong/strip = tidak diketahui
            // ──────────────────────────────────────────
            $hargaRaw     = $this->cleanString((string) ($row['Harga Perolehan'] ?? ''));
            $hargaVal     = null;
            $hargaUnknown = $hargaRaw === '' || preg_match('/^-+$/', $hargaRaw);
            if (!$hargaUnknown) {
                // Normalisasi: hapus Rp, titik sebagai pemisah ribuan, spasi
                $hargaNorm = preg_replace('/[Rp\s]/u', '', $hargaRaw);
                // Heuristik: jika ada titik dan diikuti 3 digit lalu akhir/titik lagi → ribuan
                $hargaNorm = preg_replace('/\.(?=\d{3}(?:[,.]|$))/', '', $hargaNorm);
                // Ganti koma desimal dengan titik
                $hargaNorm = str_replace(',', '.', $hargaNorm);

                if (!is_numeric($hargaNorm) || (float) $hargaNorm < 0) {
                    $rowErrors[] = ['field' => 'Harga Perolehan', 'message' => "Harga Perolehan harus berupa angka. Nilai \"{$hargaRaw}\" tidak valid."];
                } else {
                    $hargaVal = (float) $hargaNorm;
                    if ($classification) {
                        if (strtolower($classification->slug) === 'aset' && $hargaVal < 1000000) {
                            $rowErrors[] = ['field' => 'Harga Perolehan', 'message' => 'Aset harus memiliki harga perolehan >= Rp1.000.000.'];
                        } elseif (strtolower($classification->slug) === 'inventaris' && $hargaVal >= 1000000) {
                            $rowErrors[] = ['field' => 'Harga Perolehan', 'message' => 'Inventaris harus memiliki harga perolehan < Rp1.000.000.'];
                        }
                    }
                }
            }

            if (!empty($rowErrors)) {
                foreach ($rowErrors as $e) {
                    $errors[] = array_merge(['row' => $rowNum], $e);
                }
            }
        }

        return $errors;
    }

    /**
     * Import data ke database. Gunakan dalam DB::transaction.
     * Pastikan validateRows() sudah dipanggil dan tidak ada error sebelum memanggil ini.
     *
     * Setiap baris CSV dengan Jumlah=N akan menghasilkan:
     *   - Kategori 'supply' : 1 InventoryBalance saldo += N (tidak membuat Asset)
     *   - Kategori lainnya  : 1 Purchase + 1 PurchaseItem + N Asset records individual
     *
     * @return int Jumlah unit berhasil diimport (jumlah Asset yang dibuat + jumlah supply unit)
     */
    public function import(array $rows): int
    {
        // Pre-load semua master data
        $classifications = Classification::all()->keyBy(fn($c) => mb_strtolower($this->cleanString($c->name)));
        $categories      = Category::all()->keyBy(fn($c) => mb_strtolower($this->cleanString($c->name)));
        $campuses        = Campus::all()->keyBy(fn($c) => mb_strtolower($this->cleanString($c->name)));
        $locations       = Location::with('campus')->get();
        $employees       = Employee::all()->keyBy(fn($e) => mb_strtolower($this->cleanString($e->name)));

        $count = 0;

        foreach ($rows as $row) {
            $classification = $classifications->get(mb_strtolower($this->cleanString($row['Kategori Akuntansi'] ?? '')));
            $campus         = $campuses->get(mb_strtolower($this->cleanString($row['Gedung'] ?? '')));

            // Cari kategori; jika belum ada → buat otomatis dan attach ke classification
            $categoryName = $this->cleanString($row['Kategori'] ?? '');
            $category     = $categories->get(mb_strtolower($categoryName));
            if (!$category && $categoryName !== '') {
                $category = Category::create([
                    'name'        => $categoryName,
                    'code'        => strtoupper(Str::slug($categoryName, '-')),
                    'description' => 'Kategori otomatis dari import untuk ' . $categoryName,
                    'active'      => true,
                ]);
                // Attach ke classification yang sesuai
                if ($classification) {
                    $category->classifications()->syncWithoutDetaching([$classification->id]);
                }
                // Tambahkan ke koleksi in-memory
                $categories->put(mb_strtolower($categoryName), $category);
            } elseif ($category && $classification) {
                // Pastikan linkage ada meski kategori sudah ada tapi belum terhubung
                $category->classifications()->syncWithoutDetaching([$classification->id]);
            }

            $ruanganName = trim($row['Ruangan'] ?? '');

            // Cari ruangan; jika belum ada dan gedung valid → buat otomatis
            $location = null;
            if ($campus && $ruanganName !== '') {
                $location = $locations
                    ->where('campus_id', $campus->id)
                    ->first(fn($l) => mb_strtolower($l->name) === mb_strtolower($ruanganName));

                if (!$location) {
                    $location = Location::create([
                        'campus_id' => $campus->id,
                        'name'      => $ruanganName,
                    ]);
                    // Tambahkan ke koleksi in-memory agar baris berikutnya bisa menemukannya
                    $locations->push($location->load('campus'));
                }
            }

            // Cari PIC; jika belum ada → buat otomatis
            $picName = trim($row['PIC'] ?? '');
            $pic     = null;
            if ($picName !== '') {
                $pic = $employees->get(mb_strtolower($picName));
                if (!$pic) {
                    $pic = Employee::create(['name' => $picName]);
                    // Tambahkan ke koleksi in-memory agar baris berikutnya tidak membuat duplikat
                    $employees->put(mb_strtolower($picName), $pic);
                }
            }

            $ownershipVal = self::OWNERSHIP_MAP[mb_strtolower(trim($row['Sumber Dana'] ?? ''))] ?? 'company';
            $statusVal    = self::STATUS_MAP[mb_strtolower(trim($row['Status'] ?? ''))] ?? 'stock';
            $kondisiVal   = self::KONDISI_MAP[mb_strtolower(trim($row['Kondisi'] ?? ''))] ?? 'good';

            // Tahun perolehan → purchase_date: null jika kosong/strip (tidak diketahui)
            $tahunRaw     = trim((string) ($row['Tahun Perolehan'] ?? ''));
            $tahunUnknown = $tahunRaw === '' || preg_match('/^-+$/', $tahunRaw);
            $tahun        = (!$tahunUnknown && preg_match('/^\d{4}$/', $tahunRaw)) ? (int) $tahunRaw : null;
            $purchaseDate = $tahun ? "{$tahun}-01-01" : null;

            // Harga: normalisasi (kosong/strip = tidak diketahui)
            $hargaRaw     = trim((string) ($row['Harga Perolehan'] ?? ''));
            $hargaVal     = null;
            $hargaUnknown = $hargaRaw === '' || preg_match('/^-+$/', $hargaRaw);
            if (!$hargaUnknown) {
                $hargaNorm = preg_replace('/[Rp\s]/u', '', $hargaRaw);
                $hargaNorm = preg_replace('/\.(?=\d{3}(?:[,.]|$))/', '', $hargaNorm);
                $hargaNorm = str_replace(',', '.', $hargaNorm);
                if (is_numeric($hargaNorm)) {
                    $hargaVal = (float) $hargaNorm;
                }
            }

            $jumlah     = max(1, (int) trim($row['Jumlah'] ?? 1));
            $unitPrice  = $hargaVal ?? null;
            $totalPrice = $unitPrice !== null ? $unitPrice * $jumlah : null;

            // Notes (Keterangan) + unknown info suffix
            $notesRaw    = trim($row['Keterangan'] ?? '');
            $unknownInfo = array_filter([
                $tahunUnknown ? 'tahun perolehan tidak diketahui' : null,
                $hargaUnknown ? 'harga perolehan tidak diketahui' : null,
            ]);
            if ($unknownInfo) {
                $suffix   = '(' . implode(', ', $unknownInfo) . ')';
                $notesRaw = $notesRaw !== '' ? $notesRaw . ' ' . $suffix : $suffix;
            }

            // Shared base data for Asset records
            $baseAssetData = [
                'classification_id' => $classification?->id,
                'category_id'       => $category?->id,
                'name'              => trim($row['Nama Barang'] ?? ''),
                'brand'             => trim($row['Merk/Tipe'] ?? '') ?: null,
                'serial_number'     => trim($row['Nomor Seri'] ?? '') ?: null,
                'unit'              => trim($row['Satuan'] ?? '') ?: null,
                'ownership'         => $ownershipVal,
                'campus_id'         => $campus?->id,
                'location_id'       => $location?->id,
                'pic_id'            => $pic?->id,
                'status'            => $statusVal,
                'kondisi'           => $kondisiVal,
                'notes'             => $notesRaw ?: null,
            ];

            // ──────────────────────────────────────────────────────────────
            // SUPPLY PATH: update InventoryBalance saldo — no Asset records
            // ──────────────────────────────────────────────────────────────
            if ($classification && strtolower($classification->slug) === 'persediaan-barang') {
                // Create Purchase header for traceability
                $purchase = Purchase::create([
                    'purchase_date' => $purchaseDate,
                    'ownership'     => $ownershipVal,
                    'total_amount'  => $totalPrice,
                ]);

                // firstOrCreate balance grouped by category + name + location
                $balanceName = trim($row['Nama Barang'] ?? '');
                $balance = InventoryBalance::where([
                    'category_id' => $category->id,
                    'name'        => $balanceName,
                    'location_id' => $location?->id,
                ])->first();

                $isNewBalance = false;

                if (!$balance) {
                    $balance = InventoryBalance::create([
                        'category_id' => $category->id,
                        'name'        => $balanceName,
                        'location_id' => $location?->id,
                        'campus_id' => $campus?->id,
                        'quantity'  => 0,
                        'master_barcode' => SupplyBarcodeGenerator::generateMaster(),
                        'latest_sequence' => 0,
                        'has_pure_master_unit' => false,
                    ]);
                    $isNewBalance = true;
                }

                // Add purchased quantity to the running balance
                $balance->increment('quantity', $jumlah);

                // Record purchase item linked to the balance
                $purchaseItem = PurchaseItem::create([
                    'purchase_id'        => $purchase->id,
                    'inventory_balance_id' => $balance->id,
                    'category_id'        => $category->id,
                    'classification_id'  => $classification?->id,
                    'name'               => $balanceName,
                    'quantity'           => $jumlah,
                    'unit'               => trim($row['Satuan'] ?? '') ?: null,
                    'unit_price'         => $unitPrice,
                    'total_price'        => $totalPrice,
                    'is_capitalized'     => false, // Supply is never capitalized
                ]);

                // BARCODE LOGIC
                if ($isNewBalance && $jumlah === 1) {
                    $balance->update(['has_pure_master_unit' => true]);
                    $balance->units()->create([
                        'purchase_item_id' => $purchaseItem->id,
                        'sub_barcode' => $balance->master_barcode,
                        'status' => 'available'
                    ]);
                } else {
                    $subBarcodes = SupplyBarcodeGenerator::generateSub($balance, $jumlah);
                    $unitRecords = [];
                    foreach ($subBarcodes as $sb) {
                        $unitRecords[] = [
                            'inventory_balance_id' => $balance->id,
                            'purchase_item_id' => $purchaseItem->id,
                            'sub_barcode' => $sb,
                            'status' => 'available',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    \App\Models\InventoryBalanceUnit::insert($unitRecords);
                }

                $count += $jumlah; // Count as stock units added
                continue;
            }

            // ──────────────────────────────────────────────────────────────
            // ASSET / INVENTORY PATH: create N individual Asset records
            // ──────────────────────────────────────────────────────────────

            // Create Purchase header
            $purchase = Purchase::create([
                'purchase_date' => $purchaseDate,
                'ownership'     => $ownershipVal,
                'total_amount'  => $totalPrice,
            ]);

            // Create PurchaseItem (1 per row in CSV, regardless of quantity)
            $purchaseItem = PurchaseItem::create([
                'purchase_id'       => $purchase->id,
                'category_id'       => $category?->id,
                'classification_id' => $classification?->id,
                'name'              => trim($row['Nama Barang'] ?? ''),
                'quantity'          => $jumlah,
                'unit'              => trim($row['Satuan'] ?? '') ?: null,
                'unit_price'        => $unitPrice,
                'total_price'       => $totalPrice,
                // Business Rule: Capitalization based on UNIT PRICE >= Rp1.000.000 and classification ASET
                'is_capitalized'    => PurchaseItem::isCapitalizable($unitPrice, $classification),
            ]);

            // Create N individual Asset records — 1 record = 1 physical unit
            for ($i = 0; $i < $jumlah; $i++) {
                // Each unit gets its own unique inventory_number (SKU)
                // Kode selalu di-generate otomatis oleh sistem (tidak pakai dari file)
                $inventoryNumber = InventoryNumberGenerator::generate($classification, $category);

                $assetData = $baseAssetData;
                $assetData['inventory_number'] = $inventoryNumber;
                $assetData['purchase_item_id'] = $purchaseItem->id;
                // Barcode is auto-generated by AssetObserver::creating()

                // For quantities > 1, only the first unit gets the original serial_number.
                // Subsequent units will have serial_number = null to avoid duplicate conflicts.
                if ($i > 0) {
                    $assetData['serial_number'] = null;
                }

                Asset::create($assetData);
                $count++;
            }
        }

        return $count;
    }


    // ──────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────

    private function parseCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException("Tidak dapat membuka file CSV.");
        }

        // Detect BOM (UTF-8 BOM)
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Auto-detect delimiter
        $firstLine = fgets($handle);
        rewind($handle);
        if ($bom === "\xEF\xBB\xBF") {
            fread($handle, 3); // skip BOM again
        }

        $delimiter = ',';
        $tabCount  = substr_count($firstLine, "\t");
        $semiCount = substr_count($firstLine, ';');
        $commaCount = substr_count($firstLine, ',');
        if ($tabCount > $commaCount && $tabCount > $semiCount) {
            $delimiter = "\t";
        } elseif ($semiCount > $commaCount) {
            $delimiter = ';';
        }

        $headers = null;
        $rows    = [];
        $rowNum  = 1;

        while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNum++;
            if ($headers === null) {
                $headers = array_map('trim', $line);
                continue; // baris pertama = header, skip
            }

            // Skip baris kosong
            if (empty(array_filter($line, fn($v) => trim($v) !== ''))) {
                continue;
            }

            $row = [];
            foreach ($headers as $i => $h) {
                $row[$h] = $line[$i] ?? '';
            }
            $row['_row_number'] = $rowNum;
            $rows[] = $row;
        }

        fclose($handle);

        if ($headers === null) {
            throw new \RuntimeException("File CSV kosong atau tidak dapat dibaca.");
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    private function parseSpreadsheet(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet       = $spreadsheet->getActiveSheet();
        $allRows     = $sheet->toArray(null, true, true, false);

        if (empty($allRows)) {
            throw new \RuntimeException("File spreadsheet kosong.");
        }

        // Baris pertama = header
        $headers = array_map('trim', array_map(fn($v) => (string) $v, $allRows[0]));

        $rows = [];
        $totalRows = count($allRows);

        for ($i = 1; $i < $totalRows; $i++) {
            $line   = $allRows[$i];
            $rowNum = $i + 1; // +1 karena array 0-indexed, +1 untuk header

            // Skip baris kosong
            if (empty(array_filter($line, fn($v) => trim((string) $v) !== ''))) {
                continue;
            }

            $row = [];
            foreach ($headers as $j => $h) {
                $val = $line[$j] ?? '';

                // Handle Excel date serial number untuk kolom Tahun Perolehan
                if (mb_strtolower($h) === 'tahun perolehan' && is_numeric($val) && (int) $val > 1000 && (int) $val < 3000) {
                    // Mungkin sudah berupa tahun angka (2024), biarkan
                    $row[$h] = (string) (int) $val;
                } elseif (mb_strtolower($h) === 'tahun perolehan' && is_float($val) && $val > 40000) {
                    // Excel date serial → konversi ke tahun
                    try {
                        $date    = ExcelDate::excelToDateTimeObject($val);
                        $row[$h] = $date->format('Y');
                    } catch (\Throwable) {
                        $row[$h] = (string) $val;
                    }
                } else {
                    $row[$h] = (string) $val;
                }
            }

            $row['_row_number'] = $rowNum;
            $rows[] = $row;
        }

        return ['headers' => $headers, 'rows' => $rows];
    }
}
