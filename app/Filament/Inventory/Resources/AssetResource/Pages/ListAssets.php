<?php

namespace App\Filament\Inventory\Resources\AssetResource\Pages;

use App\Filament\Inventory\Resources\AssetResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Illuminate\Support\HtmlString;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ── Import Asset ──────────────────────────────────────
            Action::make('importAsset')
                ->label('Import Asset')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->modalHeading('Import Data Asset')
                ->modalDescription('Upload file CSV, Excel (.xlsx), atau Excel (.xls) yang berisi data asset.')
                ->modalWidth('2xl')
                ->form([
                    Components\Placeholder::make('import_info')
                        ->label('')
                        ->content(new HtmlString('
                            <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-200">
                                <ul class="list-disc list-inside space-y-1 text-xs">
                                    <li>Pastikan format header sesuai template.</li>
                                    <li><strong>Gedung</strong> menentukan pilihan <strong>Ruangan</strong>.</li>
                                    <li><strong>Kode</strong> tidak boleh duplikat.</li>
                                    <li><strong>Jumlah</strong> & <strong>Harga Perolehan</strong> harus berupa angka.</li>
                                </ul>
                            </div>
                        ')),

                    Components\FileUpload::make('import_file')
                        ->label('Pilih File')
                        ->required()
                        ->acceptedFileTypes([
                            'text/csv',
                            'text/plain',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/csv',
                            'application/octet-stream',
                        ])
                        ->maxSize(10240)
                        ->helperText('Maks. 10MB. Format: CSV, XLSX, XLS.'),
                ])
                ->action(function (array $data) {
                    // Prevent timeout for large files (PhpSpreadsheet is slow)
                    set_time_limit(300);

                    /** @var TemporaryUploadedFile|null $uploadedFile */
                    $uploadedFile = $data['import_file'] ?? null;

                    if (!$uploadedFile) {
                        Notification::make()->title('File tidak ditemukan')->danger()->send();
                        return;
                    }

                    // Filament FileUpload mengembalikan string path (karena storeFiles(false) dihapus)
                    if (is_string($uploadedFile)) {
                        $tempPath  = \Illuminate\Support\Facades\Storage::path($uploadedFile);
                        $extension = strtolower(pathinfo($uploadedFile, PATHINFO_EXTENSION));
                    } else {
                        $tempPath  = $uploadedFile->getRealPath();
                        $extension = strtolower($uploadedFile->getClientOriginalExtension());
                    }

                    if (!in_array($extension, ['csv', 'xlsx', 'xls'])) {
                        Notification::make()->title('Format file tidak didukung')->danger()->send();
                        return;
                    }

                    $importService = app(\App\Services\AssetImportService::class);

                    try {
                        $parsed  = $importService->parseFile($tempPath, $extension);
                        $headers = $parsed['headers'];
                        $rows    = $parsed['rows'];
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Gagal membaca file')->body($e->getMessage())->danger()->persistent()->send();
                        return;
                    }

                    $headerErrors = $importService->validateHeaders($headers);
                    if (!empty($headerErrors)) {
                        Notification::make()
                            ->title('Header file tidak valid')
                            ->body(implode("\n", $headerErrors))
                            ->danger()
                            ->persistent()
                            ->send();
                        return;
                    }

                    if (empty($rows)) {
                        Notification::make()->title('File kosong')->warning()->send();
                        return;
                    }

                    $rowErrors = $importService->validateRows($rows);
                    if (!empty($rowErrors)) {
                        $grouped = [];
                        foreach ($rowErrors as $err) {
                            $grouped[$err['row']][] = "{$err['field']}: {$err['message']}";
                        }

                        $invalidCount = count($grouped);
                        $bodyParts = [
                            "Total: ".count($rows)." baris",
                            "Bermasalah: {$invalidCount} baris\n",
                        ];

                        $preview = array_slice($grouped, 0, 5, true);
                        foreach ($preview as $rowNum => $msgs) {
                            $bodyParts[] = "Baris {$rowNum}:\n• " . implode("\n• ", array_unique($msgs));
                        }

                        if (count($grouped) > 5) {
                            $bodyParts[] = "\n... dan " . (count($grouped) - 5) . " baris lainnya.";
                        }

                        $bodyParts[] = "\nPerbaiki file dan coba import ulang.";

                        Notification::make()
                            ->title("Import gagal — {$invalidCount} baris invalid")
                            ->body(implode("\n", $bodyParts))
                            ->danger()
                            ->persistent()
                            ->send();
                        return;
                    }

                    try {
                        $count = DB::transaction(fn () => $importService->import($rows));
                        Notification::make()
                            ->title('Import Berhasil')
                            ->body("{$count} data asset berhasil ditambahkan.")
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Log::error('Asset import transaction failed', ['error' => $e->getMessage()]);
                        Notification::make()
                            ->title('Import gagal')
                            ->body('Terjadi kesalahan saat menyimpan data.')
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                })
                ->modalSubmitActionLabel('Proses Import')
                ->modalCancelActionLabel('Batal'),

            // ── Download Template ────────────────────────────────
            Action::make('downloadTemplate')
                ->label('Download Template')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn () => route('asset.import.template'))
                ->openUrlInNewTab(),

            // ── Create Asset ────────────────────────────────────
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
