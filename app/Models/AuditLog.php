<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasRouteUlid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AuditLog extends Model {
    use HasRouteUlid;

    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
