<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AssetLocationHistory extends Model {
    protected $guarded = [];
    public function asset(): BelongsTo { return $this->belongsTo(Asset::class); }
    public function oldLocation(): BelongsTo { return $this->belongsTo(Location::class, 'old_location_id'); }
    public function newLocation(): BelongsTo { return $this->belongsTo(Location::class, 'new_location_id'); }
    public function oldPic(): BelongsTo { return $this->belongsTo(Employee::class, 'old_pic_id'); }
    public function newPic(): BelongsTo { return $this->belongsTo(Employee::class, 'new_pic_id'); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
