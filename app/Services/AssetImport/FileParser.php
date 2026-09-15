<?php

namespace App\Services\AssetImport;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Turns a CSV/XLSX/XLS file on disk into raw row data. Pure I/O — no
 * business-rule knowledge (that's RowValidator's job).
 */
class FileParser
{
    /**
     * Parse file dan return array of row data (associative, keyed by header name).
     * Supports: .csv, .xlsx, .xls
     *
     * @param string      $filePath  Path ke file temporary
     * @param string|null $extension Extension eksplisit (opsional, fallback ke auto-detect dari path)
     *
     * @throws \RuntimeException jika format file tidak didukung
     */
    public function parse(string $filePath, ?string $extension = null): array
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
