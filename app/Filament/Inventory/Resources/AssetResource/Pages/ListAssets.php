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

    public function getHeading(): string | \Illuminate\Contracts\Support\Htmlable
    {
        $heading = parent::getHeading();
        return new HtmlString('
            <style>
                @media (max-width: 639px) {
                    .fi-header {
                        flex-direction: row !important;
                        justify-content: space-between !important;
                        align-items: center !important;
                    }
                    .fi-header-actions-ctn {
                        margin-top: 0 !important;
                    }
                }
            </style>
            ' . $heading
        );
    }

    protected function getHeaderActions(): array
    {
        return [ \Filament\Actions\ActionGroup::make([
            // ── Cetak Barcode Massal ──────────────────────────────────────
            Action::make('bulkPrintBarcode')
                ->label('Cetak Barcode')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->modalHeading('Cetak Barcode & Checklist Massal')
                ->modalWidth('2xl')
                ->form([
                    Components\Select::make('campus_id')
                        ->label('Gedung / Kampus')
                        ->options(\App\Models\Campus::pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function (callable $set) {
                            $set('location_id', null);
                            $set('ready_count', 0);
                            $set('error_count', 0);
                            $set('error_list', '');
                        })
                        ->required(),

                    Components\Select::make('location_id')
                        ->label('Ruangan')
                        ->options(fn (callable $get) => \App\Models\Location::when($get('campus_id'), fn($q) => $q->where('campus_id', $get('campus_id')))->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required()
                        ->disabled(fn (callable $get) => blank($get('campus_id')))
                        ->afterStateUpdated(function (callable $set, $state) {
                            if (!$state) {
                                $set('ready_count', 0);
                                $set('error_count', 0);
                                $set('error_list', '');
                                return;
                            }
                            $hasBarcodeCount = \App\Models\Asset::where('location_id', $state)->whereNotNull('barcode')->count();
                            $noBarcodeAssets = \App\Models\Asset::where('location_id', $state)
                                ->whereNull('barcode')
                                ->get(['inventory_number', 'name']);
                            
                            $set('ready_count', $hasBarcodeCount);
                            $set('error_count', $noBarcodeAssets->count());
                            
                            $list = '';
                            foreach ($noBarcodeAssets->take(10) as $asset) {
                                $list .= "<li>{$asset->inventory_number} - {$asset->name}</li>";
                            }
                            if ($noBarcodeAssets->count() > 10) {
                                $list .= "<li>...dan " . ($noBarcodeAssets->count() - 10) . " barang lainnya</li>";
                            }
                            $set('error_list', $list);
                        }),

                    Components\Placeholder::make('summary')
                        ->label('Status')
                        ->visible(fn (callable $get) => filled($get('location_id')))
                        ->content(function (callable $get) {
                            $ready = $get('ready_count') ?? 0;
                            if ($ready == 0) {
                                return new HtmlString("<div class='text-red-600'>Tidak ada barang yang memiliki barcode pada ruangan ini.</div>");
                            }
                            return new HtmlString("<div class='text-green-600 font-bold'>{$ready} barang akan dicetak.</div>");
                        }),

                    Components\Placeholder::make('errors')
                        ->label('Bermasalah')
                        ->visible(fn (callable $get) => ($get('error_count') ?? 0) > 0)
                        ->content(function (callable $get) {
                            $count = $get('error_count');
                            $list = $get('error_list');
                            return new HtmlString("<div class='rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800'><p><strong>{$count} barang belum memiliki barcode dan tidak dapat dicetak:</strong></p><ul class='list-disc pl-5 mt-2'>{$list}</ul></div>");
                        }),
                ])
                ->action(function (array $data, Action $action, \Livewire\Component $livewire) {
                    $readyCount = \App\Models\Asset::where('location_id', $data['location_id'])->whereNotNull('barcode')->count();
                    if ($readyCount == 0) {
                        Notification::make()->title('Gagal')->body('Tidak ada barang dengan barcode di ruangan ini.')->danger()->send();
                        return;
                    }
                    
                    $url = route('asset.bulk.print', ['location_id' => $data['location_id']]);
                    $livewire->js("window.open('{$url}', '_blank');");
                })
                ->modalSubmitActionLabel('Cetak')
                ->modalCancelActionLabel('Batal'),

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

            Action::make('downloadTemplate')
                ->label('Download Template')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn () => route('asset.import.template'))
                ->openUrlInNewTab(),
            ])
            ->label('Opsi Tambahan')
            ->icon('heroicon-m-ellipsis-vertical')
            ->color('gray')
            ->tooltip('Opsi Tambahan'),

            // ── Create Asset ────────────────────────────────────
            \Filament\Actions\CreateAction::make()
                ->label('New Barang/Aset')
                ->labeledFrom('lg')
                ->icon('heroicon-o-plus'),
        ];
    }
}
