<?php
namespace App\Policies;
use App\Models\ApprovalRequest;
use App\Models\User;
class ApprovalRequestPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('status.view'); }
    public function view(User $user, ApprovalRequest $model): bool { return $user->hasPermissionTo('status.view'); }
    public function create(User $user): bool { return false; }
    public function update(User $user, ApprovalRequest $model): bool { return false; }
    public function delete(User $user, ApprovalRequest $model): bool { return false; }
}
