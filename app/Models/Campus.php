<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Campus extends Model {
    use SoftDeletes;
    protected $guarded = [];
    public function locations(): HasMany { return $this->hasMany(Location::class); }
    public function assets(): HasMany { return $this->hasMany(Asset::class); }
}
