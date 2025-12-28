<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventory>
 */
class InventoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $locations = ['Main Warehouse', 'Studio Storage', 'Retail Store', 'Back Office', 'Showroom'];

        return [
            'name' => fake()->unique()->randomElement($locations),
        ];
    }
}
