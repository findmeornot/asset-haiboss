<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'classification_id' => function () {
                return \App\Models\Classification::factory()->create()->id;
            },
            'category_id' => function (array $attributes) {
                $category = \App\Models\Category::factory()->create();
                $category->classifications()->attach($attributes['classification_id']);
                return $category->id;
            },
            'campus_id' => function () {
                return \App\Models\Campus::factory()->create()->id;
            },
            'location_id' => function (array $attributes) {
                return \App\Models\Location::factory()->create(['campus_id' => $attributes['campus_id']])->id;
            },
            'name' => $this->faker->words(3, true),
            'inventory_number' => 'INV-' . $this->faker->unique()->numerify('####'),
            'status' => 'stock',
            'ownership' => 'yayasan',
        ];
    }
}
