<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Employee extends Model {
    use SoftDeletes;
    protected $guarded = [];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function assets(): HasMany { return $this->hasMany(Asset::class, 'pic_id'); }
}
