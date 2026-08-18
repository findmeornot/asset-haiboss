<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AssetPhoto extends Model
{
    protected $guarded = [];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
    
    protected static function booted()
    {
        static::created(function (AssetPhoto $photo) {
            \App\Services\AuditLogger::log(
                action: \App\Enums\AuditAction::PHOTO_UPLOADED,
                model: $photo->asset,
                metadata: [
                    'photo_id' => $photo->id,
                    'file_path' => $photo->file_path,
                ]
            );
        });

        static::deleted(function (AssetPhoto $photo) {
            // Delete actual file
            if ($photo->file_path && Storage::disk('public')->exists($photo->file_path)) {
                Storage::disk('public')->delete($photo->file_path);
            }

            \App\Services\AuditLogger::log(
                action: \App\Enums\AuditAction::PHOTO_DELETED,
                model: $photo->asset,
                metadata: [
                    'photo_id' => $photo->id,
                    'file_path' => $photo->file_path,
                ]
            );
        });
    }
}
