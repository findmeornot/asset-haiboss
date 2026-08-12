<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Classification extends Model {
    protected $guarded = [];
    public function categories(): BelongsToMany { return $this->belongsToMany(Category::class, 'category_classification'); }
    public function assets(): HasMany { return $this->hasMany(Asset::class); }
}
