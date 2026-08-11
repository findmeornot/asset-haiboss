<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class StockOpnameItem extends Model {
    protected $guarded = [];
    public function stockOpname(): BelongsTo { return $this->belongsTo(StockOpname::class); }
    public function asset(): BelongsTo { return $this->belongsTo(Asset::class); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function checkedBy(): BelongsTo { return $this->belongsTo(User::class, 'checked_by'); }
    public function expectedLocation(): BelongsTo { return $this->belongsTo(Location::class, 'expected_location_id'); }
    public function actualLocation(): BelongsTo { return $this->belongsTo(Location::class, 'actual_location_id'); }
}
