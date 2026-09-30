<?php

namespace App\Filament\Support;

use App\Models\InventoryBalance;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Ubah atribut identitas baris BHP (kategori / ruangan). Baris BHP unik per
 * (category_id, name, brand, location_id), jadi kalau perubahan bikin baris ini
 * identik dengan baris lain, keduanya digabung: qty dijumlah, unit & pembelian
 * dipindah ke baris tujuan, baris asal dihapus. Semua dalam satu transaksi
 * dengan row lock supaya pembelian/mutasi yang jalan bersamaan menunggu.
 */
class InventoryBalanceMerger
{
    /**
     * @param  array<string, mixed>  $changes  kolom InventoryBalance yang diubah
     * @return bool true kalau digabung ke baris yang sudah ada, false kalau cuma diubah
     */
    public static function apply(InventoryBalance $record, array $changes, string $auditAction): bool
    {
        return DB::transaction(function () use ($record, $changes, $auditAction) {
            $balance = InventoryBalance::lockForUpdate()->findOrFail($record->id);
            $old = $balance->only([...array_keys($changes), 'quantity']);

            $identity = array_merge(
                $balance->only(['category_id', 'name', 'brand', 'location_id']),
                array_intersect_key($changes, array_flip(['category_id', 'name', 'brand', 'location_id'])),
            );

            $target = InventoryBalance::query()
                ->where('id', '!=', $balance->id)
                ->where($identity)
                ->lockForUpdate()
                ->first();

            if (! $target) {
                $balance->update($changes);
                AuditLogger::log($auditAction, $balance, $old, $balance->only([...array_keys($changes), 'quantity']));

                return false;
            }

            $target->increment('quantity', $balance->quantity);
            $balance->units()->update(['inventory_balance_id' => $target->id]);
            $balance->purchaseItems()->update(['inventory_balance_id' => $target->id]);
            $balance->delete();

            AuditLogger::log($auditAction, $target, $old, ['merged_into' => $target->id, 'quantity' => $target->quantity]);

            return true;
        });
    }
}
