<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasRouteUlid;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mutation extends Model
{
    use HasRouteUlid, HasFactory, SoftDeletes;

    protected $guarded = ['id'];
    
    protected $casts = [
        'mutation_date' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(MutationItem::class);
    }

    public function sourceCampus(): BelongsTo
    {
        return $this->belongsTo(Campus::class, 'source_campus_id');
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'source_location_id');
    }

    public function sourcePic(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'source_pic_id');
    }

    public function destinationCampus(): BelongsTo
    {
        return $this->belongsTo(Campus::class, 'destination_campus_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function destinationPic(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'destination_pic_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    protected static function booted(): void
    {
        static::creating(function (Mutation $mutation) {
            if (empty($mutation->mutation_number)) {
                $prefix = strtoupper(substr($mutation->type ?? 'MUT', 0, 3));
                $mutation->mutation_number = $prefix . '-' . date('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(5));
            }
        });
        
        static::updated(function (Mutation $movement) {
            $changes = $movement->getChanges();
            $original = array_intersect_key($movement->getOriginal(), $changes);
            
            $action = \App\Enums\AuditAction::MUTATION_UPDATED ?? 'mutation_updated';
            if ($movement->wasChanged('status')) {
                if ($movement->status === 'approved') {
                    $action = \App\Enums\AuditAction::MUTATION_APPROVED ?? 'mutation_approved';
                } elseif ($movement->status === 'rejected') {
                    $action = \App\Enums\AuditAction::MUTATION_REJECTED ?? 'mutation_rejected';
                } elseif ($movement->status === 'completed') {
                    $action = \App\Enums\AuditAction::MUTATION_COMPLETED ?? 'mutation_completed';
                }
            }

            $metadata = [
                'snapshot' => [
                    'type' => $movement->type,
                    'source_location' => $movement->sourceLocation->name ?? null,
                    'destination_location' => $movement->destinationLocation->name ?? null,
                    'requester' => $movement->requestedBy->name ?? null,
                    'approver' => $movement->approvedBy->name ?? null,
                    'reason' => $movement->reason ?? null,
                    'status_before' => $original['status'] ?? $movement->getOriginal('status'),
                    'status_after' => $movement->status,
                ]
            ];

            $reason = request()->input('reject_reason') ?? request()->input('approval_note');

            if (class_exists(\App\Services\AuditLogger::class)) {
                \App\Services\AuditLogger::log($action, $movement, $original, $changes, $reason, null, null, $metadata);
            }
        });

        static::created(function (Mutation $movement) {
            $metadata = [
                'snapshot' => [
                    'type' => $movement->type,
                    'source_location' => $movement->sourceLocation->name ?? null,
                    'destination_location' => $movement->destinationLocation->name ?? null,
                    'requester' => $movement->requestedBy->name ?? null,
                    'reason' => $movement->reason ?? null,
                    'status' => $movement->status,
                ]
            ];
            
            if (class_exists(\App\Services\AuditLogger::class)) {
                \App\Services\AuditLogger::log(\App\Enums\AuditAction::MUTATION_CREATED ?? 'mutation_created', $movement, null, $movement->toArray(), null, null, null, $metadata);
            }
        });
    }
}
