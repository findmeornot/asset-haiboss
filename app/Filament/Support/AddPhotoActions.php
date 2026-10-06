<?php

namespace App\Filament\Support;

use App\Models\Asset;
use App\Models\InventoryBalance;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Bulk action "Tambah Foto Barang" -- menambahkan satu foto ke beberapa
 * aset sekaligus. Foto existing TIDAK dihapus (append-only) kecuali
 * barang sudah punya 3 foto (maxFiles) dan user mencentang opsi timpa.
 *
 * Hanya memproses baris bertipe Asset (bukan BHP/Persediaan).
 * Penyimpanan menggunakan mekanisme yang sama dengan AssetScanner
 * dan AssetResource (disk S3, tabel asset_photos).
 */
class AddPhotoActions
{
    public static function bulkAction(): BulkAction
    {
        return BulkAction::make('addPhotoBulk')
            ->label('')
            ->icon('heroicon-o-camera')
            ->tooltip('Tambah Foto')
            ->color('success')
            ->modalHeading('Tambah Foto Barang')
            ->modalDescription('Foto akan ditambahkan ke semua barang yang dipilih. Foto existing tidak dihapus.')
            ->modalWidth('lg')
            ->form(function (Collection $records): array {
                $assets = static::resolveAssets($records);
                $totalAssets = $assets->count();

                if ($totalAssets === 0) {
                    return [
                        Placeholder::make('no_assets')
                            ->hiddenLabel()
                            ->content('Tidak ada Aset (non-BHP) yang dipilih. Aksi ini hanya berlaku untuk Aset.'),
                    ];
                }

                // Hitung aset yang sudah punya foto max (3)
                $assetsAtMax = $assets->filter(fn ($a) => $a->photos->count() >= 3)->count();

                return [
                    Placeholder::make('summary')
                        ->hiddenLabel()
                        ->content(function () use ($totalAssets, $assetsAtMax) {
                            $msg = "Foto akan ditambahkan ke {$totalAssets} barang.";
                            if ($assetsAtMax > 0) {
                                $msg .= " {$assetsAtMax} barang sudah memiliki 3 foto (maksimum) — foto baru akan dilewati untuk barang tersebut kecuali kamu mengizinkan penimpaan.";
                            }
                            return $msg;
                        }),

                    FileUpload::make('new_photos')
                        ->label('Pilih Foto')
                        ->multiple()
                        ->maxFiles(3)
                        ->image()
                        ->imageEditor()
                        ->imageResizeMode('contain')
                        ->imageResizeTargetWidth('2000')
                        ->imageResizeTargetHeight('2000')
                        ->maxSize(5120)
                        ->disk('s3')
                        ->directory('asset-photos')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->helperText('Pilih 1–3 foto. Format: JPG, PNG, WebP. Maks 5MB per file.')
                        ->panelLayout('grid')
                        // Tanpa atribut 'capture', browser mobile akan menampilkan
                        // menu pilihan: "Ambil Foto" (kamera) ATAU "Pilih dari Galeri"
                        ->extraInputAttributes(['accept' => 'image/*'])
                        ->required(),

                    Toggle::make('allow_overwrite')
                        ->label('Izinkan menimpa foto jika barang sudah penuh (3 foto)')
                        ->helperText('Jika diaktifkan, foto lama akan dihapus dari barang yang sudah memiliki 3 foto sebelum foto baru ditambahkan.')
                        ->default(false)
                        ->visible($assetsAtMax > 0),
                ];
            })
            ->action(function (Collection $records, array $data) {
                $assets = static::resolveAssets($records);

                if ($assets->isEmpty()) {
                    Notification::make()->title('Tidak ada Aset yang dipilih')->warning()->send();
                    return;
                }

                $newPhotoPaths = array_values($data['new_photos'] ?? []);
                if (empty($newPhotoPaths)) {
                    Notification::make()->title('Tidak ada foto yang dipilih')->warning()->send();
                    return;
                }

                $allowOverwrite = (bool) ($data['allow_overwrite'] ?? false);
                $disk = Storage::disk('s3');

                $added   = 0;
                $skipped = 0;

                foreach ($assets as $asset) {
                    $asset->load('photos');
                    $currentCount = $asset->photos->count();
                    $maxFiles     = 3;

                    if ($currentCount >= $maxFiles) {
                        if (! $allowOverwrite) {
                            $skipped++;
                            continue;
                        }
                        // Hapus semua foto existing agar foto baru bisa masuk
                        foreach ($asset->photos as $photo) {
                            $photo->delete(); // trigger observer: hapus file + audit log
                        }
                        $currentCount = 0;
                    }

                    $availableSlots = $maxFiles - $currentCount;
                    $pathsToAdd     = array_slice($newPhotoPaths, 0, $availableSlots);

                    // Tentukan sort_order mulai setelah foto existing
                    $startOrder = $currentCount;
                    foreach ($pathsToAdd as $offset => $path) {
                        $asset->photos()->create([
                            'file_path'  => $path,
                            'file_size'  => $disk->exists($path) ? $disk->size($path) : null,
                            'mime_type'  => $disk->exists($path) ? $disk->mimeType($path) : null,
                            'sort_order' => $startOrder + $offset,
                        ]);
                    }

                    $added++;
                }

                $parts = [];
                if ($added > 0) {
                    $parts[] = "Foto ditambahkan ke {$added} barang";
                }
                if ($skipped > 0) {
                    $parts[] = "{$skipped} barang dilewati (sudah 3 foto, opsi timpa tidak aktif)";
                }

                $title = implode('. ', $parts) . '.';
                $added > 0
                    ? Notification::make()->title($title)->success()->send()
                    : Notification::make()->title($title)->warning()->send();
            })
            ->modalSubmitActionLabel('Tambah Foto')
            ->modalCancelActionLabel('Batal')
            ->deselectRecordsAfterCompletion();
    }

    /**
     * @return Collection<int, Asset>
     */
    protected static function resolveAssets(Collection $records): Collection
    {
        if ($records->isEmpty()) {
            return collect();
        }

        if ($records->first() instanceof Asset) {
            return $records->load('photos');
        }

        if ($records->first() instanceof InventoryBalance) {
            return collect(); // BHP tidak punya foto per unit
        }

        // UnifiedItem
        return Asset::whereIn('id', $records->where('row_type', 'asset')->pluck('raw_id'))
            ->with('photos')
            ->get();
    }
}
