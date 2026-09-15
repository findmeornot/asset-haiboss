<?php

namespace App\Services\AssetImport;

use App\Models\Asset;

/**
 * Per-row business validation for the asset import. Pure dry-run — never
 * writes to the database (a missing Category/Location/PIC is not an error
 * here, because Importer will create it on the fly during the real import).
 */
class RowValidator
{
    /**
     * Validasi semua baris data. Return array of errors per baris.
     * Format: [ ['row' => N, 'field' => 'X', 'message' => '...'], ... ]
     *
     * @param array $rows  Raw rows dari FileParser::parse()
     */
    public function validate(array $rows): array
    {
        $master = MasterDataResolver::preload();
        $classifications = $master['classifications'];
        $categories      = $master['categories'];
        $campuses        = $master['campuses'];
        $locations       = $master['locations'];

        $errors = [];
        $seenCodes = [];

        foreach ($rows as $rowIndex => $row) {
            $rowNum   = $row['_row_number'] ?? ($rowIndex + 2); // +2 karena baris 1 = header
            $rowErrors = [];

            // ──────────────────────────────────────────
            // 0. Kategori Akuntansi (classification_id) — resolve lebih dulu.
            //    Diperlukan untuk mengetahui apakah baris ini Barang Habis Pakai,
            //    karena Kode Barang sepenuhnya tidak relevan untuk baris tersebut.
            // ──────────────────────────────────────────
            $classificationName = ValueNormalizer::cleanString($row['Kategori Akuntansi'] ?? '');
            $classification     = null;
            if (empty($classificationName)) {
                $rowErrors[] = ['field' => 'Kategori Akuntansi', 'message' => 'Kategori Akuntansi tidak boleh kosong.'];
            } else {
                $classification = ValueNormalizer::resolveClassification($classifications, $classificationName);
                if (!$classification) {
                    $rowErrors[] = ['field' => 'Kategori Akuntansi', 'message' => "Kategori Akuntansi \"{$classificationName}\" tidak ditemukan di master data."];
                }
            }
            $isSupplyRow = $classification && strtolower($classification->slug) === 'barang-habis-pakai';

            // ──────────────────────────────────────────
            // 1. Kode — HANYA untuk UPDATE asset existing (opsional).
            //    Kode Barang aset baru SELALU di-generate otomatis oleh sistem — kolom Kode
            //    tidak pernah dipakai untuk memilih/menetapkan Kode Barang aset baru.
            //    Barang Habis Pakai tidak menggunakan Kode Barang Asset sama sekali,
            //    jadi seluruh validasi kolom Kode diabaikan untuk baris tersebut.
            // ──────────────────────────────────────────
            $kode = ValueNormalizer::cleanString((string) ($row['Kode'] ?? ''));
            $isUpdate = false;
            $kodeProvided = $kode !== '';

            if ($kodeProvided && !$isSupplyRow) {
                // Format check
                if (!preg_match('/^INV\d{7}$/', $kode)) {
                    $rowErrors[] = ['field' => 'Kode', 'message' => "Format Kode Barang tidak valid. Harus diawali 'INV' diikuti 7 digit angka (contoh: INV0000239)."];
                } else {
                    // Duplicate check in file
                    if (isset($seenCodes[$kode])) {
                        $rowErrors[] = ['field' => 'Kode', 'message' => "Kode Barang \"{$kode}\" duplikat dengan baris ke-{$seenCodes[$kode]} di dalam file."];
                    } else {
                        $seenCodes[$kode] = $rowNum;
                    }

                    // Check DB existence and soft delete
                    $existingAsset = Asset::withTrashed()->where('inventory_number', $kode)->first();
                    if ($existingAsset) {
                        if ($existingAsset->trashed()) {
                            $rowErrors[] = ['field' => 'Kode', 'message' => "Kode Barang \"{$kode}\" sudah digunakan oleh aset yang telah dihapus. Silakan pulihkan aset tersebut secara manual terlebih dahulu."];
                        } else {
                            $isUpdate = true;
                        }
                    } else {
                        // Kode diisi tapi tidak ditemukan — TIDAK boleh diperlakukan sebagai
                        // insert baru dengan Kode eksplisit. Kode Barang aset baru harus selalu
                        // auto-generate; kosongkan kolom Kode untuk itu.
                        $rowErrors[] = ['field' => 'Kode', 'message' => "Kode Barang \"{$kode}\" tidak ditemukan. Kosongkan kolom Kode untuk membuat aset baru secara otomatis, atau isi dengan Kode Barang milik aset yang sudah ada untuk update."];
                    }
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
                if ($kodeProvided && !$isSupplyRow && $jumlahInt > 1) {
                    $rowErrors[] = ['field' => 'Jumlah', 'message' => 'Jika Kode Barang diisi, Jumlah tidak boleh lebih dari 1.'];
                }
            }

            // ──────────────────────────────────────────
            // 6. Tahun Perolehan — opsional (kosong / strip = tidak diketahui)
            // ──────────────────────────────────────────
            $tahunRaw = trim((string) ($row['Tahun Perolehan'] ?? ''));
            $tahun    = null;
            // Anggap kosong atau strip/dash sebagai "tidak diketahui"
            $tahunUnknown = $tahunRaw === '' || preg_match('/^-+$/', $tahunRaw);
            if (!$tahunUnknown) {
                $parsedDate = ValueNormalizer::parseImportDate($tahunRaw);
                if (!$parsedDate) {
                    $rowErrors[] = ['field' => 'Tahun Perolehan', 'message' => "Tahun Perolehan harus berupa 4 digit tahun (contoh: 2024) atau format tanggal (contoh: 14/03/2026 atau 2026-03-14). Nilai \"{$tahunRaw}\" tidak valid."];
                } else {
                    $yr = (int) date('Y', strtotime($parsedDate));
                    if ($yr < 1900 || $yr > (int) date('Y') + 5) {
                        $rowErrors[] = ['field' => 'Tahun Perolehan', 'message' => "Tahun Perolehan \"{$tahunRaw}\" (Tahun {$yr}) di luar rentang yang wajar."];
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
                $ownershipVal = ValueNormalizer::OWNERSHIP_MAP[mb_strtolower($sumberDanaRaw)] ?? null;
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
                if (!isset(ValueNormalizer::STATUS_MAP[mb_strtolower($statusRaw)])) {
                    $validStatus = implode(', ', array_unique(array_keys(ValueNormalizer::STATUS_MAP)));
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
                if (!isset(ValueNormalizer::KONDISI_MAP[mb_strtolower($kondisiRaw)])) {
                    $validKondisi = implode(', ', array_unique(array_keys(ValueNormalizer::KONDISI_MAP)));
                    $rowErrors[]  = ['field' => 'Kondisi', 'message' => "Kondisi \"{$kondisiRaw}\" tidak valid. Gunakan salah satu dari: {$validKondisi}."];
                }
            }

            // ──────────────────────────────────────────
            // 12. Harga Perolehan — opsional, kosong/strip = tidak diketahui
            // ──────────────────────────────────────────
            $hargaRaw     = ValueNormalizer::cleanString((string) ($row['Harga Perolehan'] ?? ''));
            $hargaVal     = null;
            $hargaUnknown = $hargaRaw === '' || preg_match('/^-+$/', $hargaRaw);
            if (!$hargaUnknown) {
                $hargaVal = ValueNormalizer::parseCurrency($hargaRaw);

                if ($hargaVal === null || $hargaVal < 0) {
                    $rowErrors[] = ['field' => 'Harga Perolehan', 'message' => "Harga Perolehan harus berupa angka. Nilai \"{$hargaRaw}\" tidak valid."];
                } else {
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
}
