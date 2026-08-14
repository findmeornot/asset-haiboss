<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasRouteUlid;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Role extends Model {
    use HasRouteUlid;

    protected $guarded = [];
    public function permissions(): BelongsToMany { return $this->belongsToMany(Permission::class); }
    public function users(): BelongsToMany { return $this->belongsToMany(User::class); }
}
