<?php

namespace App\Services;

use App\Services\AssetImport\FileParser;
use App\Services\AssetImport\HeaderValidator;
use App\Services\AssetImport\Importer;
use App\Services\AssetImport\RowValidator;
use App\Services\AssetImport\ValueNormalizer;

/**
 * Thin facade over the AssetImport collaborators (FileParser, HeaderValidator,
 * RowValidator, Importer, ValueNormalizer) — kept at this class name/namespace
 * because it's the entry point resolved via the container in the Filament
 * import pages and instantiated directly (`new AssetImportService()`) in tests.
 *
 * Usage: parseFile() → validateHeaders() → validateRows() → (wrap in
 * DB::transaction) import().
 */
class AssetImportService
{
    public const REQUIRED_HEADERS = HeaderValidator::REQUIRED_HEADERS;
    public const OWNERSHIP_MAP    = ValueNormalizer::OWNERSHIP_MAP;
    public const STATUS_MAP       = ValueNormalizer::STATUS_MAP;
    public const KONDISI_MAP      = ValueNormalizer::KONDISI_MAP;

    private FileParser $fileParser;
    private HeaderValidator $headerValidator;
    private RowValidator $rowValidator;
    private Importer $importer;

    public function __construct()
    {
        $this->fileParser      = new FileParser();
        $this->headerValidator = new HeaderValidator();
        $this->rowValidator    = new RowValidator();
        $this->importer        = new Importer();
    }

    /**
     * Parse file dan return array of row data (associative, keyed by header name).
     * Supports: .csv, .xlsx, .xls
     *
     * @throws \RuntimeException jika format file tidak didukung
     */
    public function parseFile(string $filePath, ?string $extension = null): array
    {
        return $this->fileParser->parse($filePath, $extension);
    }

    /**
     * Validasi header file. Return array of error strings (kosong = valid).
     */
    public function validateHeaders(array $headers): array
    {
        return $this->headerValidator->validate($headers);
    }

    /**
     * Validasi semua baris data. Return array of errors per baris.
     * Format: [ ['row' => N, 'field' => 'X', 'message' => '...'], ... ]
     *
     * @param array $rows Raw rows dari parseFile()
     */
    public function validateRows(array $rows): array
    {
        return $this->rowValidator->validate($rows);
    }

    /**
     * Import data ke database. Gunakan dalam DB::transaction.
     * Pastikan validateRows() sudah dipanggil dan tidak ada error sebelum memanggil ini.
     *
     * @return int Jumlah unit berhasil diimport (jumlah Asset yang dibuat + jumlah supply unit)
     */
    public function import(array $rows): int
    {
        return $this->importer->import($rows);
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
        return ValueNormalizer::parseImportDate($dateStr);
    }
}
