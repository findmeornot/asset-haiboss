<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait InvalidatesUnifiedItemCache
{
    public static function bootInvalidatesUnifiedItemCache()
    {
        static::saved(function () {
            Cache::increment('unified_items_version');
        });

        static::deleted(function () {
            Cache::increment('unified_items_version');
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function () {
                Cache::increment('unified_items_version');
            });
        }
    }
}
