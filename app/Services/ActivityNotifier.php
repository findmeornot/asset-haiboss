<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\User;
use App\Notifications\ActivityNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Pengirim notifikasi aktivitas barang.
 *
 * Semua event alur Lapor Barang Datang → Pengecekan lewat sini supaya penentuan
 * penerima (petugas inventaris + pelapor) cuma ditulis sekali.
 */
class ActivityNotifier
{
    public function __construct(private RealtimePusher $pusher) {}

    /**
     * Permission yang menandai seseorang ikut mengurus barang masuk, jadi perlu
     * tahu setiap laporan/pengecekan baru.
     */
    private const PETUGAS_PERMISSION = 'assets.update';

    /**
     * OB melaporkan barang datang — petugas inventaris perlu melengkapi data
     * & menentukan lokasi penempatannya.
     */
    public function barangDilaporkan(Asset $asset, ?User $actor = null): void
    {
        $this->kirim(
            $this->petugas()->merge($this->pelapor($asset)),
            $actor,
            new ActivityNotification(
                ActivityNotification::CATEGORY_PENERIMAAN,
                'Laporan Barang Datang Baru',
                $this->ringkas($asset) . ' dilaporkan'
                    . ($actor ? ' oleh ' . $actor->name : '')
                    . ' dan menunggu kelengkapan data.',
                '/lapor-barang-datang',
                $this->meta($asset),
            ),
        );
    }

    /**
     * Data & lokasi final sudah dilengkapi petugas — barang siap dicek OB.
     */
    public function siapDicek(Asset $asset, ?User $actor = null): void
    {
        $this->kirim(
            $this->pelapor($asset),
            $actor,
            new ActivityNotification(
                ActivityNotification::CATEGORY_UNBOXING,
                'Barang Siap Dicek',
                $this->ringkas($asset) . ' sudah dilengkapi datanya. Lokasi penempatan: '
                    . $this->lokasi($asset) . '.',
                '/pengecekan/' . $asset->ulid,
                $this->meta($asset),
            ),
        );
    }

    /**
     * OB menyelesaikan pengecekan: barcode terdaftar & nomor aset terbit.
     */
    public function pengecekanSelesai(Asset $asset, ?User $actor = null): void
    {
        $nomor = $asset->inventory_number ? ' Nomor aset ' . $asset->inventory_number . ' terbit.' : '';

        $this->kirim(
            $this->petugas()->merge($this->pelapor($asset)),
            $actor,
            new ActivityNotification(
                ActivityNotification::CATEGORY_PENEMPATAN,
                'Pengecekan Barang Selesai',
                $this->ringkas($asset) . ' selesai dicek di ' . $this->lokasi($asset) . '.' . $nomor,
                '/detail-barang/' . $asset->ulid,
                $this->meta($asset),
            ),
        );
    }

    /**
     * Kirim ke penerima unik, tanpa pelaku aksinya sendiri (dia sudah tahu).
     *
     * @param  Collection<int, User>  $penerima
     */
    private function kirim(Collection $penerima, ?User $actor, ActivityNotification $notification): void
    {
        $penerima = $penerima
            ->filter(fn (User $user) => $actor === null || $user->id !== $actor->id)
            ->unique('id')
            ->values();

        if ($penerima->isEmpty()) {
            return;
        }

        // Patokan waktu untuk mengambil row yang baru saja dibuat; dimundurkan
        // sedetik supaya beda presisi jam DB tidak membuat row-nya terlewat.
        $sejak = now()->subSecond();

        Notification::send($penerima, $notification);

        // Dorong ke BFF supaya drawer & badge user langsung berubah tanpa polling.
        $this->pusher->pushNotifikasiBaru($penerima, $sejak);
    }

    /**
     * Petugas yang berhak mengurus barang: Superadmin + pemilik permission
     * `assets.update` (langsung maupun lewat role).
     *
     * @return Collection<int, User>
     */
    private function petugas(): Collection
    {
        return User::query()
            ->where(function ($q) {
                $q->whereHas('roles', fn ($r) => $r->where('name', 'Superadmin'))
                    ->orWhereHas('permissions', fn ($p) => $p->where('name', self::PETUGAS_PERMISSION))
                    ->orWhereHas('roles.permissions', fn ($p) => $p->where('name', self::PETUGAS_PERMISSION));
            })
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function pelapor(Asset $asset): Collection
    {
        $pelapor = $asset->reported_by ? User::find($asset->reported_by) : null;

        return $pelapor ? collect([$pelapor]) : collect();
    }

    /** Sebutan barang yang enak dibaca; nama barang bisa belum diisi admin. */
    private function ringkas(Asset $asset): string
    {
        $nama = $asset->name ?: ($asset->keterangan ?: 'Barang tanpa nama');

        return Str::limit(trim($nama), 60);
    }

    private function lokasi(Asset $asset): string
    {
        $parts = array_filter([$asset->campus?->name, $asset->location?->name]);

        return $parts ? implode(' → ', $parts) : 'lokasi belum ditentukan';
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(Asset $asset): array
    {
        return [
            'asset_ulid' => $asset->ulid,
            'inventory_number' => $asset->inventory_number,
            'status' => $asset->status,
        ];
    }
}
