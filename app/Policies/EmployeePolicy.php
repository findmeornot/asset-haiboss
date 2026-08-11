<?php
namespace App\Policies;
use App\Models\Employee;
use App\Models\User;
class EmployeePolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('employees.view'); }
    public function view(User $user, Employee $model): bool { return $user->hasPermissionTo('employees.view'); }
    public function create(User $user): bool { return $user->hasPermissionTo('employees.create'); }
    public function update(User $user, Employee $model): bool { return $user->hasPermissionTo('employees.update'); }
    public function delete(User $user, Employee $model): bool { return $user->hasPermissionTo('employees.delete'); }
}
