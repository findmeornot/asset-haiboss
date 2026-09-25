<?php

namespace App\Support;

use App\Notifications\ActivityNotification;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Bentuk JSON satu notifikasi untuk frontend OB.
 *
 * Dipakai bareng oleh NotificationController (REST) dan RealtimePusher (push
 * SSE) supaya payload keduanya tidak pernah beda bentuk.
 */
class NotificationPayload
{
    /** Kategori yang dikenali frontend; selain ini dinormalisasi ke `system`. */
    private const CATEGORIES = [
        ActivityNotification::CATEGORY_PENERIMAAN,
        ActivityNotification::CATEGORY_UNBOXING,
        ActivityNotification::CATEGORY_PENEMPATAN,
        ActivityNotification::CATEGORY_SYSTEM,
    ];

    /**
     * @return array<string, mixed>
     */
    public static function from(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];
        $category = $data['category'] ?? ActivityNotification::CATEGORY_SYSTEM;

        return [
            'id' => $notification->id,
            'category' => in_array($category, self::CATEGORIES, true)
                ? $category
                : ActivityNotification::CATEGORY_SYSTEM,
            'title' => $data['title'] ?? 'Notifikasi',
            'message' => $data['message'] ?? '',
            'url' => $data['url'] ?? null,
            'meta' => $data['meta'] ?? [],
            'read' => $notification->read_at !== null,
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at,
        ];
    }
}
