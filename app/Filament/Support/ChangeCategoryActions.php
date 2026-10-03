<?php

namespace App\Filament\Support;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Classification;
use App\Models\InventoryBalance;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Bulk action "Ganti Kategori" -- koreksi kategori yang keliru (mis. salah
 * import). Cuma Superadmin. Kategori baru harus terhubung ke klasifikasi
 * barangnya (aturan yang sama dengan Asset::saving), jadi semua baris yang
 * dipilih harus satu klasifikasi (Aset / Inventaris / BHP). Klasifikasi
 * tidak berubah dan Kode Barang (SKU) tetap.
 *
 * Menerima Asset, InventoryBalance (BHP), atau UnifiedItem (campuran). Baris
 * BHP yang jadi identik dengan baris lain setelah ganti kategori digabung
 * (lihat InventoryBalanceMerger).
 */
class ChangeCategoryActions
{
    public static function bulkAction(): BulkAction
    {
        return BulkAction::make('changeCategoryBulk')
            ->label('Ganti Kategori')
            ->hiddenLabel(fn () => Auth::user()?->hasRole('superadmin') || Auth::user()?->hasRole('Superadmin'))
            ->tooltip(fn () => (Auth::user()?->hasRole('superadmin') || Auth::user()?->hasRole('Superadmin')) ? 'Ganti Kategori' : null)
            ->icon('heroicon-o-tag')
            ->color('warning')
            ->visible(fn () => Auth::user()?->hasRole('Superadmin') ?? false)
            ->authorize(fn () => Auth::user()?->hasRole('Superadmin') ?? false)
            ->modalHeading('Ganti Kategori (Koreksi Data)')
            ->modalDescription('Untuk memperbaiki kategori yang keliru. Klasifikasi dan Kode Barang tidak berubah. Stok BHP yang jadi identik (kategori, nama, merk, ruangan) akan DIGABUNG dan tidak bisa dibatalkan.')
            ->modalWidth('lg')
            ->form(function (Collection $records): array {
                $classificationId = static::commonClassificationId($records);

                return [
                    Placeholder::make('info')
                        ->hiddenLabel()
                        ->visible($classificationId === null)
                        ->content('Barang yang dipilih berbeda klasifikasi (Aset / Inventaris / BHP). Pilih barang dengan klasifikasi yang sama.'),

                    Select::make('category_id')
                        ->label('Kategori Baru')
                        ->options(fn () => $classificationId
                            ? Category::whereHas('classifications', fn ($q) => $q->whereKey($classificationId))->orderBy('name')->pluck('name', 'id')
                            : [])
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabled($classificationId === null),
                ];
            })
            ->action(function (Collection $records, array $data) {
                $classificationId = static::commonClassificationId($records);
                $categoryId = (int) $data['category_id'];

                $linked = $classificationId && Category::whereKey($categoryId)
                    ->whereHas('classifications', fn ($q) => $q->whereKey($classificationId))
                    ->exists();

                if (! $linked) {
                    Notification::make()->title('Kategori tidak sesuai dengan klasifikasi barang yang dipilih.')->danger()->send();

                    return;
                }

                $changed = 0;
                $merged = 0;

                try {
                    DB::transaction(function () use ($records, $categoryId, &$changed, &$merged) {
                        foreach (static::resolveAssets($records) as $asset) {
                            $asset->update(['category_id' => $categoryId]);
                            $changed++;
                        }

                        foreach (static::resolveBalances($records) as $balance) {
                            InventoryBalanceMerger::apply($balance, ['category_id' => $categoryId], 'updated') ? $merged++ : $changed++;
                        }
                    });
                } catch (\Throwable $e) {
                    Notification::make()->title('Gagal mengganti kategori')->body($e->getMessage())->danger()->send();

                    return;
                }

                $message = "{$changed} item diganti kategorinya";
                if ($merged > 0) {
                    $message .= ", {$merged} BHP digabung dengan stok yang sudah ada";
                }

                Notification::make()->title($message)->success()->send();
            })
            ->modalSubmitActionLabel('Ganti Kategori')
            ->modalCancelActionLabel('Batal')
            ->deselectRecordsAfterCompletion();
    }

    /**
     * Klasifikasi yang dipakai semua baris terpilih, atau null kalau campuran / tidak ada.
     */
    protected static function commonClassificationId(Collection $records): ?int
    {
        $ids = static::resolveAssets($records)->pluck('classification_id')->filter();

        if (static::resolveBalances($records)->isNotEmpty()) {
            $ids->push(Classification::where('slug', 'barang-habis-pakai')->value('id'));
        }

        $ids = $ids->filter()->unique()->values();

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    /**
     * @return Collection<int, Asset>
     */
    protected static function resolveAssets(Collection $records): Collection
    {
        if ($records->first() instanceof Asset) {
            return $records;
        }

        if ($records->first() instanceof InventoryBalance) {
            return collect();
        }

        return Asset::whereIn('id', $records->where('row_type', 'asset')->pluck('raw_id'))->get();
    }

    /**
     * @return Collection<int, InventoryBalance>
     */
    protected static function resolveBalances(Collection $records): Collection
    {
        if ($records->first() instanceof InventoryBalance) {
            return $records;
        }

        if ($records->first() instanceof Asset) {
            return collect();
        }

        return InventoryBalance::whereIn('id', $records->where('row_type', 'supply')->pluck('raw_id'))->get();
    }
}
