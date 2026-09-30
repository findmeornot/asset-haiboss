<?php

namespace App\Filament\Support;

use App\Models\Asset;
use App\Models\Campus;
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
            ->modalDescription('Untuk memperbaiki data lokasi yang keliru tanpa proses mutasi. Perubahan tetap tercatat di riwayat lokasi.')
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

                foreach (static::resolveAssets($records) as $asset) {
                    $asset->update([
                        'campus_id' => $data['campus_id'],
                        'location_id' => $data['location_id'],
                    ]);
                    $moved++;
                }

                Notification::make()
                    ->title("{$moved} aset dipindahkan ke ruangan baru")
                    ->success()
                    ->send();
            })
            ->modalSubmitActionLabel('Pindahkan')
            ->modalCancelActionLabel('Batal')
            ->deselectRecordsAfterCompletion();
    }

    /**
     * @return Collection<int, Asset>
     */
    protected static function resolveAssets(Collection $records): Collection
    {
        if ($records->first() instanceof Asset) {
            return $records;
        }

        return Asset::whereIn('id', $records->where('row_type', 'asset')->pluck('raw_id'))->get();
    }
}
