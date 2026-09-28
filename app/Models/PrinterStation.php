<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrinterStation extends Model
{
    protected $fillable = ['name', 'station_key', 'api_token', 'last_seen_at'];
    protected $hidden = ['api_token'];
    protected $casts = ['last_seen_at' => 'datetime'];

    public function isOnline(int $thresholdSeconds = 15): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->diffInSeconds(now()) <= $thresholdSeconds;
    }
}
