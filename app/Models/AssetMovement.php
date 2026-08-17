<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasRouteUlid;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMovement extends Model
{
    use HasRouteUlid;

    use HasFactory, SoftDeletes;

    protected $guarded = [];
    
    protected $casts = [
        'movement_date' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (AssetMovement $movement) {
            \App\Services\AuditLogger::log(\App\Enums\AuditAction::MUTATION_CREATED, $movement, null, $movement->toArray());
        });

        static::updated(function (AssetMovement $movement) {
            $changes = $movement->getChanges();
            $original = array_intersect_key($movement->getOriginal(), $changes);
            
            $action = \App\Enums\AuditAction::MUTATION_UPDATED;
            if ($movement->wasChanged('status')) {
                if ($movement->status === 'approved') {
                    $action = \App\Enums\AuditAction::MUTATION_APPROVED;
                } elseif ($movement->status === 'rejected') {
                    $action = \App\Enums\AuditAction::MUTATION_REJECTED;
                } elseif ($movement->status === 'completed') {
                    $action = \App\Enums\AuditAction::MUTATION_COMPLETED;
                }
            }

            \App\Services\AuditLogger::log($action, $movement, $original, $changes);
        });

        static::saving(function (AssetMovement $movement) {
            if ($movement->source_campus_id && $movement->source_location_id) {
                $linkedSource = \App\Models\Location::whereKey($movement->source_location_id)
                    ->where('campus_id', $movement->source_campus_id)
                    ->exists();
                if (! $linkedSource) {
                    throw new \InvalidArgumentException('Lokasi asal tidak berada di Gedung asal yang sesuai.');
                }
            }

            if ($movement->destination_campus_id && $movement->destination_location_id) {
                $linkedDest = \App\Models\Location::whereKey($movement->destination_location_id)
                    ->where('campus_id', $movement->destination_campus_id)
                    ->exists();
                if (! $linkedDest) {
                    throw new \InvalidArgumentException('Lokasi tujuan tidak berada di Gedung tujuan yang sesuai.');
                }
            }
        });
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
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
}
