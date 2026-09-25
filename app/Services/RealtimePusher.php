<?php

namespace App\Services;

use App\Models\User;
use App\Support\NotificationPayload;
use Carbon\CarbonInterface;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use function Illuminate\Support\defer;

/**
 * Push notifikasi realtime ke BFF (Express) lewat webhook internal.
 *
 * BFF yang memegang koneksi SSE ke browser — Laravel cuma mengabari BFF tiap
 * ada notifikasi baru / perubahan jumlah belum dibaca, jadi frontend tidak
 * perlu polling sama sekali.
 *
 * Request-nya di-defer (jalan setelah response dikirim) dan sengaja fail-silent:
 * realtime itu bonus, kegagalannya tidak boleh menggagalkan aksi user — data
 * tetap aman di table `notifications` dan tetap terbaca lewat REST.
 */
class RealtimePusher
{
    /** Toleransi selisih jam Laravel ↔ BFF saat memverifikasi signature. */
    public const SIGNATURE_TOLERANCE = 300;

    /**
     * Kabari penerima bahwa ada notifikasi baru.
     *
     * @param  Collection<int, User>  $penerima
     * @param  CarbonInterface  $sejak  batas waktu pengiriman barusan
     */
    public function pushNotifikasiBaru(Collection $penerima, CarbonInterface $sejak): void
    {
        if (! $this->aktif() || $penerima->isEmpty()) {
            return;
        }

        $ids = $penerima->pluck('id')->all();
        $morph = $penerima->first()->getMorphClass();

        $rows = DatabaseNotification::query()
            ->where('notifiable_type', $morph)
            ->whereIn('notifiable_id', $ids)
            ->where('created_at', '>=', $sejak)
            ->orderBy('created_at')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $unread = $this->jumlahBelumDibaca($morph, $ids);

        $events = $rows->map(fn (DatabaseNotification $row) => [
            'type' => 'notification',
            'user_id' => (int) $row->notifiable_id,
            'unread_count' => $unread[$row->notifiable_id] ?? 0,
            'notification' => NotificationPayload::from($row),
        ])->all();

        $this->kirim($events);
    }

    /**
     * Sinkronkan jumlah belum dibaca milik satu user — dipakai setelah aksi
     * baca/hapus supaya tab & perangkat lain user yang sama ikut berubah.
     */
    public function pushSinkronisasi(User $user): void
    {
        if (! $this->aktif()) {
            return;
        }

        $this->kirim([[
            'type' => 'sync',
            'user_id' => (int) $user->id,
            'unread_count' => $user->unreadNotifications()->count(),
        ]]);
    }

    /**
     * Jumlah notifikasi belum dibaca per user dalam satu query.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function jumlahBelumDibaca(string $morph, array $ids): array
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', $morph)
            ->whereIn('notifiable_id', $ids)
            ->whereNull('read_at')
            ->selectRaw('notifiable_id, COUNT(*) as total')
            ->groupBy('notifiable_id')
            ->pluck('total', 'notifiable_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    private function aktif(): bool
    {
        return (bool) config('services.bff.push_url') && (bool) config('services.bff.push_secret');
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    private function kirim(array $events): void
    {
        $body = json_encode(['events' => $events], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($body === false) {
            Log::warning('[RealtimePusher] Payload gagal di-encode, push dilewati.');

            return;
        }

        $url = (string) config('services.bff.push_url');
        $timestamp = (string) now()->getTimestamp();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, (string) config('services.bff.push_secret'));

        // Jalan setelah response dikirim ke user — BFF lambat/mati tidak
        // menambah latensi request.
        defer(function () use ($url, $body, $timestamp, $signature) {
            try {
                Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Push-Timestamp' => $timestamp,
                    'X-Push-Signature' => 'sha256=' . $signature,
                ])
                    ->timeout((int) config('services.bff.push_timeout', 3))
                    ->connectTimeout(2)
                    ->withBody($body, 'application/json')
                    ->post($url)
                    ->throw();
            } catch (\Throwable $e) {
                Log::warning('[RealtimePusher] Gagal push ke BFF: ' . $e->getMessage());
            }
        });
    }
}
