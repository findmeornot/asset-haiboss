<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

trait HasRouteUlid
{
    use HasUlids;

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
