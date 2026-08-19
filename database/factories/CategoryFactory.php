<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'code' => $this->faker->unique()->regexify('[A-Z]{3}'),
            'useful_life' => $this->faker->numberBetween(1, 10),
        ];
    }
}
