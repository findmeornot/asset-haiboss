<?php

namespace App\Filament\Support;

use App\Models\Asset;
use App\Models\InventoryBalance;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChangeStatusActions
{
    public static function bulkAction(): BulkAction
    {
        return BulkAction::make('changeStatusBulk')
            ->label('Ubah Status')
            ->hiddenLabel()
            ->tooltip('Ubah Status')
            ->icon('heroicon-o-arrow-path-rounded-square')
            ->color('warning')
            // Only show for superadmin
            ->visible(fn () => Auth::user()?->hasRole('superadmin') || Auth::user()?->hasRole('Superadmin'))
            ->authorize(fn () => Auth::user()?->hasRole('superadmin') || Auth::user()?->hasRole('Superadmin'))
            ->modalHeading('Ubah Status Barang')
            ->modalDescription('Mengubah status untuk semua barang yang dipilih. Khusus Aset.')
            ->modalWidth('md')
            ->form([
                Select::make('new_status')
                    ->label('Status Baru')
                    ->options([
                        'stock'                    => 'Stok (Gudang)',
                        'active'                   => 'Aktif / Digunakan',
                        'borrowed'                 => 'Dipinjam',
                        'maintenance'              => 'Dalam Perbaikan',
                        'lost'                     => 'Hilang (Butuh Approval)',
                        'sold'                     => 'Terjual',
                        'disposed'                 => 'Dihapuskan / Musnah',
                        'administratively_deleted' => 'Penghapusan Administratif (Butuh Approval)',
                        'destroyed'                => 'Dimusnahkan (Butuh Approval)',
                    ])
                    ->required(),
                Textarea::make('reason')
                    ->label('Alasan Perubahan')
                    ->required()
            ])
            ->action(function (Collection $records, array $data) {
                $newStatus = $data['new_status'];
                $reason = $data['reason'];

                $changed = 0;
                $pending = 0;

                try {
                    DB::transaction(function () use ($records, $newStatus, $reason, &$changed, &$pending) {
                        foreach (static::resolveAssets($records) as $asset) {
                            $lockedAsset = Asset::where('id', $asset->id)->lockForUpdate()->first();

                            if (in_array($newStatus, ['lost', 'destroyed', 'administratively_deleted'])) {
                                $hasPending = \App\Models\ApprovalRequest::where('status', 'pending')
                                    ->where('request_type', 'status_change')
                                    ->whereJsonContains('payload->asset_id', $lockedAsset->id)
                                    ->exists();

                                if ($hasPending) {
                                    continue; // Skip if already pending
                                }

                                \App\Models\ApprovalRequest::create([
                                    'request_type' => 'status_change',
                                    'requested_by' => Auth::id(),
                                    'status'       => 'pending',
                                    'reason'       => $reason,
                                    'payload'      => json_encode([
                                        'asset_id'    => $lockedAsset->id,
                                        'new_status'  => $newStatus,
                                        'old_status'  => $lockedAsset->status,
                                        'new_kondisi' => $lockedAsset->kondisi,
                                        'old_kondisi' => $lockedAsset->kondisi,
                                    ])
                                ]);
                                $pending++;
                            } else {
                                request()->merge(['status_change_reason' => $reason]);
                                $lockedAsset->update([
                                    'status' => $newStatus,
                                ]);
                                $changed++;
                            }
                        }
                    });
                } catch (\Throwable $e) {
                    Notification::make()->title('Gagal mengubah status')->body($e->getMessage())->danger()->send();
                    return;
                }

                if ($changed > 0 || $pending > 0) {
                    $msg = [];
                    if ($changed > 0) $msg[] = "{$changed} barang diubah statusnya";
                    if ($pending > 0) $msg[] = "{$pending} barang menunggu persetujuan (status kritis)";
                    Notification::make()->title(implode(', ', $msg))->success()->send();
                } else {
                    Notification::make()->title('Tidak ada barang yang diubah')->warning()->send();
                }
            })
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

        if ($records->first() instanceof InventoryBalance) {
            return collect();
        }

        // For UnifiedItem
        return Asset::whereIn('id', $records->where('row_type', 'asset')->pluck('raw_id'))->get();
    }
}