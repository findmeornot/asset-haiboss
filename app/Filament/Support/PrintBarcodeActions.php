<?php

namespace App\Filament\Support;

use App\Models\Asset;
use App\Models\Campus;
use App\Models\Location;
use App\Models\PrinterStation;
use App\Services\BarcodeService;
use App\Services\PrintQueueService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Exceptions\Halt;
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
     * Tombol per-baris: liat preview barcode 1 Asset dulu, tanpa langsung
     * masuk antrian print.
     */
    public static function viewAction(): Action
    {
        return Action::make('viewBarcode')
            ->label('Lihat Barcode')
            ->icon('heroicon-o-qr-code')
            ->color('gray')
            ->visible(fn (Asset $record) => filled($record->barcode))
            ->modalHeading(fn (Asset $record) => "Barcode - {$record->name}")
            ->modalContent(fn (Asset $record) => new HtmlString(
                '<div style="text-align:center;padding:8px 0;">'
                .'<div style="font-size:13px;color:#6b7280;margin-bottom:4px;">'.e($record->printLocationLabel()).'</div>'
                .'<div style="font-weight:700;margin-bottom:12px;">'.e($record->name).'</div>'
                .'<div style="display:flex;justify-content:center;margin-bottom:8px;background:#fff;padding:12px;border-radius:8px;">'.app(BarcodeService::class)->generateSvg($record->barcode).'</div>'
                .'<div style="font-family:monospace;font-weight:700;">'.e($record->inventory_number).'</div>'
                .'</div>'
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup');
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
            ->modalWidth('4xl')
            ->steps([
                Step::make('Pilih Ruangan')
                    ->schema([
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
                                    $list .= '<li>...dan '.($noBarcodeAssets->count() - 10).' barang lainnya</li>';
                                }
                                $set('error_list', $list);
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
                    ->afterValidation(function (callable $get, callable $set) {
                        $ids = Asset::where('location_id', $get('location_id'))
                            ->whereNotNull('barcode')
                            ->orderBy('name')->orderBy('inventory_number')
                            ->pluck('id')->map(fn ($id) => (string) $id)->all();

                        if ($ids === []) {
                            Notification::make()->title('Gagal')->body('Tidak ada barang dengan barcode di ruangan ini.')->danger()->send();

                            throw new Halt;
                        }

                        $set('asset_ids', $ids);
                    }),
                Step::make('Pilih Barang')
                    ->schema([
                        ViewField::make('asset_ids')
                            ->hiddenLabel()
                            ->default([])
                            ->view('filament.forms.print-barcode-asset-picker')
                            ->viewData(fn (callable $get) => [
                                'assets' => Asset::where('location_id', $get('location_id'))
                                    ->whereNotNull('barcode')
                                    ->orderBy('name')
                                    ->orderBy('inventory_number')
                                    ->get(['id', 'inventory_number', 'name', 'brand']),
                            ]),

                    ]),
            ])
            ->modifyWizardUsing(fn (Wizard $wizard) => $wizard->nextAction(fn (Action $action) => $action->label('Kirim ke Antrian')))
            ->action(function (array $data) {
                $queued = app(PrintQueueService::class)->queueForLocation(
                    (int) $data['location_id'],
                    $data['printer_station'],
                    Auth::id(),
                    array_map('intval', $data['asset_ids'] ?? []),
                );

                if ($queued === 0) {
                    Notification::make()->title('Gagal')->body('Tidak ada barang yang dipilih untuk dicetak.')->danger()->send();

                    return;
                }

                Notification::make()->title("{$queued} label dikirim ke antrian print.")->success()->send();
            })
            ->modalSubmitActionLabel('Cetak Sekarang')
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
