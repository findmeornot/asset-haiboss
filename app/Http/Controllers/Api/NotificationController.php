<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RealtimePusher;
use App\Support\NotificationPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Notifikasi aktivitas milik user yang sedang login. Sumbernya table
 * `notifications` bawaan Laravel (lihat App\Notifications\ActivityNotification).
 */
class NotificationController extends Controller
{
    public function __construct(private RealtimePusher $pusher) {}

    /**
     * @return array<string, mixed>
     */
    private function serialize(DatabaseNotification $notification): array
    {
        return NotificationPayload::from($notification);
    }

    /**
     * Daftar notifikasi user, terbaru dulu.
     *
     * Query params:
     * - filter: `unread` untuk hanya yang belum dibaca (default: semua)
     * - per_page: default 20, max 100
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $query = $request->user()->notifications();

        if ($request->string('filter')->trim()->value() === 'unread') {
            $query->whereNull('read_at');
        }

        $items = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => collect($items->items())
                ->map(fn (DatabaseNotification $n) => $this->serialize($n))
                ->all(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    /**
     * Jumlah notifikasi belum dibaca — dipakai badge di bell navbar, dipanggil
     * berkala jadi sengaja dipisah dari index() yang jauh lebih berat.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->first();

        if (! $notification) {
            return response()->json([
                'message' => 'Notifikasi tidak ditemukan.',
            ], 404);
        }

        $notification->markAsRead();

        // Tab/perangkat lain milik user ini ikut menyesuaikan badge-nya.
        $this->pusher->pushSinkronisasi($request->user());

        return response()->json([
            'message' => 'Notifikasi ditandai sudah dibaca.',
            'data' => $this->serialize($notification->refresh()),
            'meta' => ['unread_count' => $request->user()->unreadNotifications()->count()],
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        $this->pusher->pushSinkronisasi($request->user());

        return response()->json([
            'message' => 'Semua notifikasi ditandai sudah dibaca.',
            'meta' => ['unread_count' => 0],
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $deleted = $request->user()->notifications()->whereKey($id)->delete();

        if (! $deleted) {
            return response()->json([
                'message' => 'Notifikasi tidak ditemukan.',
            ], 404);
        }

        $this->pusher->pushSinkronisasi($request->user());

        return response()->json([
            'message' => 'Notifikasi dihapus.',
            'meta' => ['unread_count' => $request->user()->unreadNotifications()->count()],
        ]);
    }

    /** Bersihkan seluruh notifikasi user (tombol "Bersihkan" di drawer). */
    public function clear(Request $request): JsonResponse
    {
        $request->user()->notifications()->delete();

        $this->pusher->pushSinkronisasi($request->user());

        return response()->json([
            'message' => 'Notifikasi dibersihkan.',
            'meta' => ['unread_count' => 0],
        ]);
    }
}
