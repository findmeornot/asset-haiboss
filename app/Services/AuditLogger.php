<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * Log an audit event.
     *
     * @param string $action E.g., 'created', 'updated', 'status_change', 'movement'
     * @param mixed $model The Eloquent model instance
     * @param array|null $old Old values
     * @param array|null $new New values
     * @param string|null $reason Reason for change
     */
    public static function log(string $action, $model, ?array $old = null, ?array $new = null, ?string $reason = null)
    {
        DB::table('audit_logs')->insert([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->id,
            'old_values' => $old ? json_encode($old) : null,
            'new_values' => $new ? json_encode($new) : null,
            'reason' => $reason,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
