<?php
namespace App\Policies;
use App\Models\Category;
use App\Models\User;
class CategoryPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('categories.view'); }
    public function view(User $user, Category $model): bool { return $user->hasPermissionTo('categories.view'); }
    public function create(User $user): bool { return $user->hasPermissionTo('categories.create'); }
    public function update(User $user, Category $model): bool { return $user->hasPermissionTo('categories.update'); }
    public function delete(User $user, Category $model): bool { return $user->hasPermissionTo('categories.delete'); }
}
