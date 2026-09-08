<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\InventoryBalance;
use Illuminate\Validation\ValidationException;

class MutationValidationService
{
    public const ELIGIBLE_STATUSES = ['stock', 'active'];

    public static function validateAndLockItems(string $type, array $itemsToSave, $sourceCampusId, $sourceLocationId): void
    {
        if (in_array($type, ['asset', 'inventory'])) {
            if (empty($itemsToSave)) {
                throw ValidationException::withMessages([
                    'asset_ids' => 'Barang harus dipilih.',
                ]);
            }
            
            $expectedSlug = ($type === 'asset') ? 'aset' : 'inventaris';
            
            // Lock records
            $assets = Asset::whereIn('id', $itemsToSave)->lockForUpdate()->get();
            
            if ($assets->count() !== count($itemsToSave)) {
                throw ValidationException::withMessages([
                    'asset_ids' => 'Beberapa barang tidak ditemukan.',
                ]);
            }
            
            foreach ($assets as $asset) {
                if ($asset->location_id != $sourceLocationId || $asset->campus_id != $sourceCampusId) {
                    throw ValidationException::withMessages([
                        'asset_ids' => "Barang {\$asset->name} tidak berada di lokasi asal yang dipilih.",
                    ]);
                }
                
                $slug = $asset->classification->slug ?? '';
                if ($slug !== $expectedSlug) {
                    throw ValidationException::withMessages([
                        'asset_ids' => "Barang {\$asset->name} bukan merupakan {\$expectedSlug}.",
                    ]);
                }
                
                if (!in_array($asset->status, self::ELIGIBLE_STATUSES)) {
                    throw ValidationException::withMessages([
                        'asset_ids' => "Barang {\$asset->name} memiliki status yang tidak valid untuk dimutasi.",
                    ]);
                }
            }
        } else if ($type === 'consumable') {
            if (empty($itemsToSave)) {
                throw ValidationException::withMessages([
                    'items' => 'Barang Habis Pakai harus dipilih.',
                ]);
            }
            
            foreach ($itemsToSave as $item) {
                if (!isset($item['inventory_balance_id']) || !isset($item['quantity'])) continue;
                
                $balance = InventoryBalance::where('id', $item['inventory_balance_id'])->lockForUpdate()->first();
                if (!$balance) {
                    throw ValidationException::withMessages(['items' => 'Data barang habis pakai tidak ditemukan.']);
                }
                
                if ($balance->location_id != $sourceLocationId || $balance->campus_id != $sourceCampusId) {
                    throw ValidationException::withMessages(['items' => "Persediaan {\$balance->name} tidak berada di lokasi asal."]);
                }
                
                if ($item['quantity'] <= 0) {
                    throw ValidationException::withMessages(['items' => "Kuantitas mutasi untuk {\$balance->name} harus lebih dari 0."]);
                }
                
                if ($balance->quantity < $item['quantity']) {
                    throw ValidationException::withMessages(['items' => "Stok {\$balance->name} tidak mencukupi (Tersedia: {\$balance->quantity})."]);
                }
            }
        }
    }
}
