<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Location extends Model {
    use SoftDeletes;
    protected $guarded = [];
    public function campus(): BelongsTo { return $this->belongsTo(Campus::class); }
    public function assets(): HasMany { return $this->hasMany(Asset::class); }
}
