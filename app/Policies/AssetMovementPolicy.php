<?php
namespace App\Policies;
use App\Models\AssetMovement;
use App\Models\User;
class AssetMovementPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('movements.view'); }
    public function view(User $user, AssetMovement $model): bool { return $user->hasPermissionTo('movements.view'); }
    public function create(User $user): bool { return $user->hasPermissionTo('movements.create'); }
    public function update(User $user, AssetMovement $model): bool { return $user->hasPermissionTo('movements.create'); } // Assuming requester can update
    public function delete(User $user, AssetMovement $model): bool { return false; } 
    public function approve(User $user, AssetMovement $model): bool { return $user->hasPermissionTo('movements.approve'); }
}
