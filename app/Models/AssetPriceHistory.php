<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AssetPriceHistory extends Model {
    protected $guarded = [];
    public function asset(): BelongsTo { return $this->belongsTo(Asset::class); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
