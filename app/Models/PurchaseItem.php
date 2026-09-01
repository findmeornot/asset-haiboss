<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseItem extends Model
{
    use HasFactory, SoftDeletes;

    public const CAPITALIZATION_THRESHOLD = 1000000;

    public static function isCapitalizable(?float $unitPrice, ?Classification $classification = null): bool
    {
        if ($unitPrice === null) {
            return false;
        }

        if ($classification && strtolower($classification->slug) !== 'aset') {
            return false;
        }
        return $unitPrice >= self::CAPITALIZATION_THRESHOLD;
    }

    protected $guarded = [];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'is_capitalized' => 'boolean',
        'quantity' => 'integer',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function inventoryBalance(): BelongsTo
    {
        return $this->belongsTo(InventoryBalance::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function classification(): BelongsTo
    {
        return $this->belongsTo(Classification::class);
    }
}
