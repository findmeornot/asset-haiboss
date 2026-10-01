<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\PrintJob;

/**
 * Satu-satunya tempat yang menulis ke tabel print_jobs. Semua jalur "Cetak
 * Barcode" di Filament (row/bulk/per-lokasi) manggil sini, supaya field yang
 * di-set (status, location_label, requested_by, dst) konsisten di mana pun
 * antrian print itu dibuat.
 */
class PrintQueueService
{
    public function queueAsset(Asset $asset, string $stationKey, ?int $requestedBy): PrintJob
    {
        return PrintJob::create([
            'printer_station' => $stationKey,
            'inventory_number' => $asset->inventory_number,
            'location_label' => $asset->printLocationLabel(),
            'status' => 'pending',
            'requested_by' => $requestedBy,
        ]);
    }

    /**
     * @param  iterable<Asset>  $assets
     */
    public function queueAssets(iterable $assets, string $stationKey, ?int $requestedBy): int
    {
        $count = 0;

        foreach ($assets as $asset) {
            $this->queueAsset($asset, $stationKey, $requestedBy);
            $count++;
        }

        return $count;
    }

    /**
     * Antre semua Asset berbarcode di satu Ruangan, urut Nama > Kode (sama
     * kayak urutan cetak di lembar checklist lama). $onlyAssetIds (kalau
     * diisi) membatasi ke Asset yang dicentang user di pop-up.
     *
     * @param  array<int>|null  $onlyAssetIds
     */
    public function queueForLocation(int $locationId, string $stationKey, ?int $requestedBy, ?array $onlyAssetIds = null): int
    {
        $assets = Asset::where('location_id', $locationId)
            ->whereNotNull('barcode')
            ->when($onlyAssetIds !== null, fn ($q) => $q->whereIn('id', $onlyAssetIds))
            ->orderBy('name')
            ->orderBy('inventory_number')
            ->get();

        return $this->queueAssets($assets, $stationKey, $requestedBy);
    }
}
