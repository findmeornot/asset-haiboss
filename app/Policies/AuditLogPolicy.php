<?php
namespace App\Policies;
use App\Models\AuditLog;
use App\Models\User;
class AuditLogPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('audit.view'); }
    public function view(User $user, AuditLog $model): bool { return $user->hasPermissionTo('audit.view'); }
    public function create(User $user): bool { return false; }
    public function update(User $user, AuditLog $model): bool { return false; }
    public function delete(User $user, AuditLog $model): bool { return false; }
}
