<?php

namespace App\Observers;

use App\Models\User;
use App\Services\AuditLogger;

class UserObserver
{
    public function created(User $user): void
    {
        AuditLogger::log(\App\Enums\AuditAction::USER_CREATED, $user, null, $user->toArray());
    }

    public function updated(User $user): void
    {
        $changes = $user->getChanges();
        $original = array_intersect_key($user->getOriginal(), $changes);
        
        $action = \App\Enums\AuditAction::USER_UPDATED;
        if ($user->wasChanged('is_active') && $user->is_active === false) {
            $action = \App\Enums\AuditAction::USER_DISABLED;
        }

        AuditLogger::log($action, $user, $original, $changes);
    }

    public function deleted(User $user): void
    {
        AuditLogger::log('deleted', $user, $user->toArray(), null);
    }
}
