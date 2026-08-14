<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasRouteUlid;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Campus extends Model {
    use HasRouteUlid;

    use SoftDeletes;
    protected $guarded = [];
    public function locations(): HasMany { return $this->hasMany(Location::class); }
    public function assets(): HasMany { return $this->hasMany(Asset::class); }
}
