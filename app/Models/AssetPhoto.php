<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AssetPhoto extends Model
{
    /** Slot foto pengecekan fisik barang oleh OB (lihat BarangMasukController). */
    public const TYPE_TAMPAK_DEPAN = 'tampak_depan';
    public const TYPE_TAMPAK_SAMPING = 'tampak_samping';
    public const TYPE_LABEL_SN = 'label_sn';
    public const TYPE_KARDUS = 'kardus';

    /** Urutan slot sekaligus nilai default `sort_order`. */
    public const TYPES = [
        self::TYPE_TAMPAK_DEPAN,
        self::TYPE_TAMPAK_SAMPING,
        self::TYPE_LABEL_SN,
        self::TYPE_KARDUS,
    ];

    protected $guarded = [];

    /**
     * URL publik file foto. Foto lama tersimpan di disk `public`, foto baru di
     * disk default (S3), jadi cek keberadaan file lokal dulu (murah) sebelum
     * jatuh ke URL disk default.
     */
    public function getUrlAttribute(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        if (Storage::disk('public')->exists($this->file_path)) {
            return Storage::disk('public')->url($this->file_path);
        }

        return Storage::disk(config('filesystems.default'))->url($this->file_path);
    }

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
            // Delete actual file. File bisa berada di disk `public` (upload lama)
            // atau disk default/S3 (upload Filament & API pengecekan).
            if ($photo->file_path) {
                foreach (array_unique(['public', config('filesystems.default')]) as $diskName) {
                    $disk = Storage::disk($diskName);
                    if ($disk->exists($photo->file_path)) {
                        $disk->delete($photo->file_path);
                    }
                }
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
