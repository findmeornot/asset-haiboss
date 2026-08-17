<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use App\Enums\AuditAction;

class AuditLogger
{
    public const SENSITIVE_FIELDS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'remember_token',
        'api_token',
        'access_token',
        'refresh_token',
        'secret',
        'secret_key',
        'private_key',
    ];

    public static ?string $currentBatchUuid = null;
    public static ?int $currentParentId = null;

    /**
     * Log an audit event.
     *
     * @param string|AuditAction $action
     * @param mixed $model The Eloquent model instance (nullable for system events)
     * @param array|null $old Old values
     * @param array|null $new New values
     * @param string|null $reason Reason for change
     * @param int|null $parentId Parent audit ID for bulk operations
     * @param string|null $batchUuid Batch identifier
     * @param array|null $metadata Additional JSON data
     * @param int|null $userId Provide a user ID override
     * @return \App\Models\AuditLog
     */
    public static function log(
        $action, 
        $model = null, 
        ?array $old = null, 
        ?array $new = null, 
        ?string $reason = null,
        ?int $parentId = null,
        ?string $batchUuid = null,
        ?array $metadata = null,
        ?int $userId = null
    ) {
        $actionValue = $action instanceof AuditAction ? $action->value : $action;

        if (is_array($old)) {
            $old = \Illuminate\Support\Arr::except($old, self::SENSITIVE_FIELDS);
        }

        if (is_array($new)) {
            $new = \Illuminate\Support\Arr::except($new, self::SENSITIVE_FIELDS);
        }
        
        if (is_array($metadata)) {
            $metadata = \Illuminate\Support\Arr::except($metadata, self::SENSITIVE_FIELDS);
        }

        $ipAddress = request()->ip();
        $userAgent = request()->userAgent();

        $baseMetadata = [
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ];

        if (is_array($metadata)) {
            $metadata = array_merge($baseMetadata, $metadata);
        } else {
            $metadata = $baseMetadata;
        }

        return AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $actionValue,
            'auditable_type' => $model ? get_class($model) : null,
            'auditable_id' => $model ? $model->id : null,
            'old_values' => $old,
            'new_values' => $new,
            'reason' => $reason,
            'parent_id' => $parentId ?? self::$currentParentId,
            'batch_uuid' => $batchUuid ?? self::$currentBatchUuid,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
