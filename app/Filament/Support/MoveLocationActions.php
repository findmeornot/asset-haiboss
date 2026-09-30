<?php

namespace App\Filament\Support;

use App\Models\Asset;
use App\Models\Campus;
use App\Models\InventoryBalance;
use App\Models\Location;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Bulk action "Pindah Ruangan" -- koreksi data lokasi yang salah (mis. salah
 * import / nama ruangan dobel). Beda dari Mutasi: gak lewat approval, cuma
 * boleh Superadmin. Tiap Asset di-save lewat Eloquent, jadi AssetObserver
 * tetap nyatet AssetLocationHistory + audit log.
 *
 * Menerima Asset, InventoryBalance (BHP), atau UnifiedItem (campuran).
 * Baris BHP yang bentrok dengan baris identik di ruangan tujuan (unique
 * category+nama+merk+ruangan) digabung: qty dijumlah, unit & pembelian
 * dipindah ke baris tujuan, baris asal dihapus.
 */
class MoveLocationActions
{
    public static function bulkAction(): BulkAction
    {
        return BulkAction::make('moveLocationBulk')
            ->label('Pindah Ruangan')
            ->icon('heroicon-o-arrow-right-circle')
            ->color('warning')
            ->visible(fn () => Auth::user()?->hasRole('Superadmin') ?? false)
            ->authorize(fn () => Auth::user()?->hasRole('Superadmin') ?? false)
            ->modalHeading('Pindah Ruangan (Koreksi Data)')
            ->modalDescription('Untuk memperbaiki data lokasi yang keliru tanpa proses mutasi. Perubahan tetap tercatat di riwayat lokasi. Stok BHP yang sama (kategori, nama, merk) di ruangan tujuan akan DIGABUNG dan tidak bisa dibatalkan.')
            ->modalWidth('lg')
            ->form([
                Select::make('campus_id')
                    ->label('Gedung / Kampus Tujuan')
                    ->options(fn () => Campus::pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (callable $set) => $set('location_id', null))
                    ->required(),

                Select::make('location_id')
                    ->label('Ruangan Tujuan')
                    ->options(fn (callable $get) => Location::where('campus_id', $get('campus_id'))->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabled(fn (callable $get) => blank($get('campus_id'))),
            ])
            ->action(function (Collection $records, array $data) {
                $moved = 0;
                $merged = 0;

                foreach (static::resolveAssets($records) as $asset) {
                    $asset->update([
                        'campus_id' => $data['campus_id'],
                        'location_id' => $data['location_id'],
                    ]);
                    $moved++;
                }

                foreach (static::resolveBalances($records) as $balance) {
                    static::moveBalance($balance, $data) ? $merged++ : $moved++;
                }

                $message = "{$moved} item dipindahkan ke ruangan baru";
                if ($merged > 0) {
                    $message .= ", {$merged} BHP digabung dengan stok yang sudah ada di ruangan tujuan";
                }

                Notification::make()->title($message)->success()->send();
            })
            ->modalSubmitActionLabel('Pindahkan')
            ->modalCancelActionLabel('Batal')
            ->deselectRecordsAfterCompletion();
    }

    /**
     * @return bool true kalau digabung ke baris yang sudah ada, false kalau cuma dipindah
     */
    protected static function moveBalance(InventoryBalance $record, array $data): bool
    {
        return InventoryBalanceMerger::apply($record, [
            'campus_id' => $data['campus_id'],
            'location_id' => $data['location_id'],
        ], 'location_change');
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
