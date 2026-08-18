<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AssetPurchaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'asset_id' => \App\Models\Asset::factory(),
            'total_price' => $this->faker->randomFloat(2, 100, 100000),
            'purchase_date' => now()->subYears(2),
            'invoice_number' => 'INV-' . $this->faker->numerify('#####'),
        ];
    }
}
