<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasRouteUlid;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Category extends Model {
    use HasRouteUlid;

    use SoftDeletes;
    protected $guarded = [];

    public const TYPES = [
        'asset'     => 'Aset',
        'inventory' => 'Inventaris',
        'supply'    => 'Persediaan',
    ];

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function assets(): HasMany { return $this->hasMany(Asset::class); }
    public function classifications(): BelongsToMany { return $this->belongsToMany(Classification::class, 'category_classification'); }
}
