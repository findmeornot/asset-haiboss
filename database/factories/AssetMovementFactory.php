<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AssetMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'asset_id' => \App\Models\Asset::factory(),
            'status' => 'pending',
            'requested_by' => \App\Models\User::factory(),
        ];
    }
}
