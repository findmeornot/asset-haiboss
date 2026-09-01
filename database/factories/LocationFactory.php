<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'campus_id' => \App\Models\Campus::factory(),
            'name' => $this->faker->streetName(),
        ];
    }
}
