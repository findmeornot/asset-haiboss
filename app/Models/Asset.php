<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasRouteUlid;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Asset extends Model {
    use HasRouteUlid, SoftDeletes, HasFactory;
    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (Asset $asset) {
            if ($asset->classification_id && $asset->category_id) {
                $linked = Category::query()
                    ->whereKey($asset->category_id)
                    ->whereHas('classifications', fn ($q) => $q->whereKey($asset->classification_id))
                    ->exists();

                if (! $linked) {
                    throw new \InvalidArgumentException('Kategori yang dipilih tidak terkait dengan klasifikasi yang dipilih.');
                }
            }
            if ($asset->campus_id && $asset->location_id) {
                $linkedLocation = \App\Models\Location::whereKey($asset->location_id)
                    ->where('campus_id', $asset->campus_id)
                    ->exists();

                if (! $linkedLocation) {
                    throw new \InvalidArgumentException('Lokasi/Ruangan yang dipilih tidak berada di Gedung yang sesuai.');
                }
            }
        });

        static::forceDeleting(function (Asset $asset) {
            foreach ($asset->photos as $photo) {
                if ($photo->file_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($photo->file_path)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($photo->file_path);
                }
            }
        });
    }

    public function classification(): BelongsTo { return $this->belongsTo(Classification::class); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function campus(): BelongsTo { return $this->belongsTo(Campus::class); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function pic(): BelongsTo { return $this->belongsTo(Employee::class, 'pic_id'); }
    public function purchase(): HasOne { return $this->hasOne(AssetPurchase::class); }
    public function financial(): HasOne { return $this->hasOne(AssetFinancial::class); }
    public function documents(): HasMany { return $this->hasMany(AssetDocument::class); }
    public function photos(): HasMany { return $this->hasMany(AssetPhoto::class); }
    public function statusHistories(): HasMany { return $this->hasMany(AssetStatusHistory::class); }
    public function locationHistories(): HasMany { return $this->hasMany(AssetLocationHistory::class); }
    public function priceHistories(): HasMany { return $this->hasMany(AssetPriceHistory::class); }
}
