<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasRouteUlid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class StockOpname extends Model {
    use HasRouteUlid;

    protected $guarded = [];
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function items(): HasMany { return $this->hasMany(StockOpnameItem::class); }
    public function campus(): BelongsTo { return $this->belongsTo(Campus::class); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
}
