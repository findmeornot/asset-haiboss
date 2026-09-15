<?php

namespace App\Services\AssetImport;

use App\Models\Classification;
use Illuminate\Support\Collection;

/**
 * Shared, stateless value-normalization helpers used by both RowValidator
 * and Importer, so the two never drift out of sync on how a raw Excel/CSV
 * cell is interpreted.
 */
class ValueNormalizer
{
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
     * Hapus karakter whitespace tersembunyi seperti NBSP (\xC2\xA0).
     */
    public static function cleanString(?string $str): string
    {
        if ($str === null) return '';
        return trim(preg_replace('/^[\pZ\pC]+|[\pZ\pC]+$/u', '', $str));
    }

    /**
     * Parse date string into Y-m-d format.
     * Supports:
     * - YYYY (e.g. 2026) -> 2026-01-01
     * - DD/MM/YYYY or DD-MM-YYYY (e.g. 14/03/2026) -> 2026-03-14
     * - YYYY-MM-DD or YYYY/MM/DD (e.g. 2026-03-14) -> 2026-03-14
     */
    public static function parseImportDate(string $dateStr): ?string
    {
        $dateStr = trim($dateStr);
        if (preg_match('/^\d{4}$/', $dateStr)) {
            return $dateStr . '-01-01';
        }
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $dateStr, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $dateStr, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }
        return null;
    }

    /**
     * Normalisasi string Harga Perolehan (hapus "Rp", pemisah ribuan, ganti
     * koma desimal) menjadi float. Return null kalau bukan angka yang valid —
     * pemanggil yang menentukan arti null itu (error vs "tidak diketahui").
     */
    public static function parseCurrency(string $rawHarga): ?float
    {
        $norm = preg_replace('/[Rp\s]/u', '', $rawHarga);
        // Heuristik: jika ada titik dan diikuti 3 digit lalu akhir/titik lagi → ribuan
        $norm = preg_replace('/\.(?=\d{3}(?:[,.]|$))/', '', $norm);
        // Ganti koma desimal dengan titik
        $norm = str_replace(',', '.', $norm);

        if (!is_numeric($norm)) {
            return null;
        }

        return (float) $norm;
    }

    /**
     * Helper untuk resolve classification, mendukung alias legacy untuk Supply.
     *
     * @param Collection $classifications keyed by lowercase name
     */
    public static function resolveClassification(Collection $classifications, string $name): ?Classification
    {
        $normalized = mb_strtolower($name);

        // Alias support for "Barang Habis Pakai" / "Persediaan"
        $supplyAliases = ['persediaan', 'persediaan barang', 'barang habis pakai'];
        if (in_array($normalized, $supplyAliases)) {
            return $classifications->first(function ($c) use ($supplyAliases) {
                return in_array(mb_strtolower($c->name), $supplyAliases) || in_array($c->slug, ['persediaan-barang', 'barang-habis-pakai']);
            });
        }

        return $classifications->get($normalized);
    }
}
