<?php
namespace App\Policies;
use App\Models\Campus;
use App\Models\User;
class CampusPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('campuses.view'); }
    public function view(User $user, Campus $model): bool { return $user->hasPermissionTo('campuses.view'); }
    public function create(User $user): bool { return $user->hasPermissionTo('campuses.create'); }
    public function update(User $user, Campus $model): bool { return $user->hasPermissionTo('campuses.update'); }
    public function delete(User $user, Campus $model): bool { return $user->hasPermissionTo('campuses.delete'); }
}
