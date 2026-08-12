<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Category extends Model {
    use SoftDeletes;
    protected $guarded = [];
    public function assets(): HasMany { return $this->hasMany(Asset::class); }
    public function classifications(): BelongsToMany { return $this->belongsToMany(Classification::class, 'category_classification'); }
}
