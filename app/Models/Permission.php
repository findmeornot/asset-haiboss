<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasRouteUlid;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Permission extends Model {
    use HasRouteUlid;

    protected $guarded = [];
    public function roles(): BelongsToMany { return $this->belongsToMany(Role::class); }
}
