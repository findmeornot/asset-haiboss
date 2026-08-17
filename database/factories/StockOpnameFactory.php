<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class StockOpnameFactory extends Factory
{
    public function definition(): array
    {
        return [
            'campus_id' => \App\Models\Campus::factory(),
            'name' => 'Stock Opname ' . $this->faker->monthName() . ' ' . $this->faker->year(),
            'status' => 'draft',
            'start_date' => now(),
            'end_date' => now()->addDays(7),
        ];
    }
}
