<?php

namespace App\Observers;

use App\Models\Asset;
use App\Models\AssetLocationHistory;
use App\Models\AssetPriceHistory;
use App\Models\AssetStatusHistory;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;

class AssetObserver
{
    public function created(Asset $asset): void
    {
        AuditLogger::log('created', $asset, null, $asset->toArray());
    }

    public function updated(Asset $asset): void
    {
        $userId = Auth::id();
        $changes = $asset->getChanges();
        $original = array_intersect_key($asset->getOriginal(), $changes);

        // Check for Status Change
        if ($asset->wasChanged('status')) {
            AssetStatusHistory::create([
                'asset_id' => $asset->id,
                'old_status' => $asset->getOriginal('status'),
                'new_status' => $asset->status,
                'changed_by' => $userId,
            ]);
            AuditLogger::log('status_change', $asset, $original, $changes, request()->input('status_change_reason'));
        } elseif ($asset->wasChanged('location_id') || $asset->wasChanged('pic_id') || $asset->wasChanged('campus_id')) {
            AssetLocationHistory::create([
                'asset_id' => $asset->id,
                'old_location_id' => $asset->getOriginal('location_id'),
                'new_location_id' => $asset->location_id,
                'old_pic_id' => $asset->getOriginal('pic_id'),
                'new_pic_id' => $asset->pic_id,
                'changed_by' => $userId,
            ]);
            AuditLogger::log('location_change', $asset, $original, $changes);
        } elseif ($asset->wasChanged('unit_price') || $asset->wasChanged('total_price')) {
            AssetPriceHistory::create([
                'asset_id' => $asset->id,
                'old_price' => $asset->getOriginal('total_price') ?? $asset->getOriginal('unit_price'),
                'new_price' => $asset->total_price ?? $asset->unit_price,
                'changed_by' => $userId,
            ]);
            AuditLogger::log('price_change', $asset, $original, $changes);
        } else {
            // General Update
            AuditLogger::log('updated', $asset, $original, $changes);
        }
    }

    public function deleted(Asset $asset): void
    {
        AuditLogger::log('deleted', $asset, $asset->toArray(), null);
    }

    public function restored(Asset $asset): void
    {
        AuditLogger::log('restored', $asset, null, $asset->toArray());
    }
}
