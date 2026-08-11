<?php
namespace App\Policies;
use App\Models\StockOpname;
use App\Models\User;
class StockOpnamePolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('stock_opname.view'); }
    public function view(User $user, StockOpname $model): bool { return $user->hasPermissionTo('stock_opname.view'); }
    public function create(User $user): bool { return $user->hasPermissionTo('stock_opname.create'); }
    public function update(User $user, StockOpname $model): bool { return $user->hasPermissionTo('stock_opname.update'); }
    public function delete(User $user, StockOpname $model): bool { return false; } 
}
