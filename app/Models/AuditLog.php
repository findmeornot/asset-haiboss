<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasRouteUlid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Enums\AuditAction;

class AuditLog extends Model {
    use HasRouteUlid;

    public $timestamps = false;
    protected $guarded = [];
    
    protected $casts = [
        'action'     => AuditAction::class,
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];
    
    public function user(): BelongsTo { 
        return $this->belongsTo(User::class); 
    }

    public function auditable(): MorphTo {
        return $this->morphTo();
    }

    public function parent(): BelongsTo {
        return $this->belongsTo(AuditLog::class, 'parent_id');
    }

    public function children(): HasMany {
        return $this->hasMany(AuditLog::class, 'parent_id');
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Audit logs cannot be modified.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Audit logs cannot be deleted.');
        });
    }
}
