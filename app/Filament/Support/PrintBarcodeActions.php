<?php

namespace App\Filament\Support;

use App\Models\Asset;
use App\Models\Campus;
use App\Models\Location;
use App\Models\PrinterStation;
use App\Services\PrintQueueService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/**
 * Factory tombol "Cetak Barcode" (row / bulk-selection / massal-per-lokasi)
 * yang dipakai bareng-bareng oleh AssetResource, BaseCategoryAssetResource,
 * UnifiedItemResource, ListAssets, ListUnifiedItems -- supaya wiring
 * Filament-nya (form, action, notifikasi) cuma didefinisikan sekali di sini,
 * bukan disalin-tempel di 5 tempat. Penulisan PrintJob-nya sendiri didelegasikan
 * ke PrintQueueService (pemisahan UI wiring vs business logic).
 */
class PrintBarcodeActions
{
    protected static function stationSelect(string $helperText): Select
    {
        return Select::make('printer_station')
            ->label('Printer / Station')
            ->options(fn () => PrinterStation::orderBy('name')->pluck('name', 'station_key'))
            ->required()
            ->helperText($helperText);
    }

    /**
     * Tombol per-baris: cetak 1 Asset.
     */
    public static function rowAction(): Action
    {
        return Action::make('queuePrintLabel')
            ->label('Cetak Barcode')
            ->hiddenLabel()
            ->icon('heroicon-o-printer')
            ->color('primary')
            ->visible(fn () => PrinterStation::exists())
            ->form([
                static::stationSelect('Label dikirim ke antrian, dicetak otomatis di printer yang terhubung ke station ini.'),
            ])
            ->action(function (array $data, Asset $record) {
                app(PrintQueueService::class)->queueAsset($record, $data['printer_station'], Auth::id());

                Notification::make()->title('Label dikirim ke antrian print.')->success()->send();
            });
    }

    /**
     * Tombol bulk (checkbox pilih-banyak baris di tabel). Menerima Asset
     * langsung (AssetResource/BaseCategoryAssetResource) atau UnifiedItem
     * (UnifiedItemResource, campuran baris Asset + Persediaan).
     */
    public static function bulkAction(): BulkAction
    {
        return BulkAction::make('queuePrintLabelBulk')
            ->label('Cetak Barcode')
            ->icon('heroicon-o-printer')
            ->color('primary')
            ->visible(fn () => PrinterStation::exists())
            ->form([
                static::stationSelect('Semua label diantre bareng, otomatis ke-pair 2 per baris sesuai urutan.'),
            ])
            ->action(function (array $data, Collection $records) {
                $assets = static::resolveAssets($records);

                $queued = app(PrintQueueService::class)->queueAssets($assets, $data['printer_station'], Auth::id());

                $skipped = $records->count() - $queued;
                $title = "{$queued} label dikirim ke antrian print.";
                if ($skipped > 0) {
                    $title .= " ({$skipped} dilewati: barang habis pakai / belum ada barcode)";
                }

                Notification::make()->title($title)->success()->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * Header action massal per Gedung + Ruangan, dengan live preview jumlah
     * barang yang siap dicetak (dan yang belum punya barcode).
     */
    public static function byLocationHeaderAction(): Action
    {
        return Action::make('queuePrintLabelByLocation')
            ->label('Cetak Barcode (Massal)')
            ->icon('heroicon-o-printer')
            ->color('primary')
            ->visible(fn () => PrinterStation::exists())
            ->modalHeading('Cetak Barcode - Massal per Ruangan')
            ->modalWidth('2xl')
            ->form([
                Select::make('campus_id')
                    ->label('Gedung / Kampus')
                    ->options(fn () => Campus::pluck('name', 'id'))
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

                Select::make('location_id')
                    ->label('Ruangan')
                    ->options(fn (callable $get) => Location::when($get('campus_id'), fn ($q) => $q->where('campus_id', $get('campus_id')))->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required()
                    ->disabled(fn (callable $get) => blank($get('campus_id')))
                    ->afterStateUpdated(function (callable $set, $state) {
                        if (! $state) {
                            $set('ready_count', 0);
                            $set('error_count', 0);
                            $set('error_list', '');

                            return;
                        }

                        $hasBarcodeCount = Asset::where('location_id', $state)->whereNotNull('barcode')->count();
                        $noBarcodeAssets = Asset::where('location_id', $state)
                            ->whereNull('barcode')
                            ->get(['inventory_number', 'name']);

                        $set('ready_count', $hasBarcodeCount);
                        $set('error_count', $noBarcodeAssets->count());

                        $list = '';
                        foreach ($noBarcodeAssets->take(10) as $asset) {
                            $list .= "<li>{$asset->inventory_number} - {$asset->name}</li>";
                        }
                        if ($noBarcodeAssets->count() > 10) {
                            $list .= '<li>...dan ' . ($noBarcodeAssets->count() - 10) . ' barang lainnya</li>';
                        }
                        $set('error_list', $list);
                    }),

                Placeholder::make('summary')
                    ->label('Status')
                    ->visible(fn (callable $get) => filled($get('location_id')))
                    ->content(function (callable $get) {
                        $ready = $get('ready_count') ?? 0;
                        if ($ready == 0) {
                            return new HtmlString("<div class='text-red-600'>Tidak ada barang yang memiliki barcode pada ruangan ini.</div>");
                        }

                        return new HtmlString("<div class='text-green-600 font-bold'>{$ready} barang akan dicetak.</div>");
                    }),

                Placeholder::make('errors')
                    ->label('Bermasalah')
                    ->visible(fn (callable $get) => ($get('error_count') ?? 0) > 0)
                    ->content(function (callable $get) {
                        $count = $get('error_count');
                        $list = $get('error_list');

                        return new HtmlString("<div class='rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800'><p><strong>{$count} barang belum memiliki barcode dan tidak dapat dicetak:</strong></p><ul class='list-disc pl-5 mt-2'>{$list}</ul></div>");
                    }),

                static::stationSelect('Semua label diantre bareng, otomatis ke-pair 2 per baris sesuai urutan Nama > Kode.'),
            ])
            ->action(function (array $data) {
                $queued = app(PrintQueueService::class)->queueForLocation(
                    (int) $data['location_id'],
                    $data['printer_station'],
                    Auth::id(),
                );

                if ($queued === 0) {
                    Notification::make()->title('Gagal')->body('Tidak ada barang dengan barcode di ruangan ini.')->danger()->send();

                    return;
                }

                Notification::make()->title("{$queued} label dikirim ke antrian print.")->success()->send();
            })
            ->modalSubmitActionLabel('Kirim ke Antrian')
            ->modalCancelActionLabel('Batal');
    }

    /**
     * $records isi Asset langsung, atau UnifiedItem (campuran row_type
     * 'asset'/'supply' -- 'supply' gak punya barcode per unit, dilewati).
     *
     * @return Collection<int, Asset>
     */
    protected static function resolveAssets(Collection $records): Collection
    {
        if ($records->isEmpty()) {
            return $records;
        }

        if ($records->first() instanceof Asset) {
            return $records;
        }

        $assetIds = $records->where('row_type', 'asset')->pluck('raw_id');

        return Asset::whereIn('id', $assetIds)->whereNotNull('barcode')->get();
    }
}
