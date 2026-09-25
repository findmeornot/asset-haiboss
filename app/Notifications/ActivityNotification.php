<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Notifikasi aktivitas barang (database channel) untuk aplikasi OB.
 *
 * Sengaja generic: satu class dipakai semua event alur barang, pembedanya ada
 * di `category` supaya frontend cukup memetakan kategori ke ikon/warna tanpa
 * perlu tahu nama class notifikasinya.
 */
class ActivityNotification extends Notification
{
    /** Kategori yang dikenali frontend; di luar daftar ini dianggap `system`. */
    public const CATEGORY_PENERIMAAN = 'penerimaan';

    public const CATEGORY_UNBOXING = 'unboxing';

    public const CATEGORY_PENEMPATAN = 'penempatan';

    public const CATEGORY_SYSTEM = 'system';

    /**
     * @param  string  $category  salah satu CATEGORY_*
     * @param  string|null  $url  path relatif di frontend OB, mis. `/pengecekan/{ulid}`
     * @param  array<string, mixed>  $meta  data tambahan (ulid aset, nomor aset, dst)
     */
    public function __construct(
        private string $category,
        private string $title,
        private string $message,
        private ?string $url = null,
        private array $meta = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => $this->category,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'meta' => $this->meta,
        ];
    }
}
