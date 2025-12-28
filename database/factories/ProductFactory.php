<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $productNames = [
            'Wooden Photo Frame', 'Metal Photo Frame', 'Acrylic Photo Frame',
            'Leather Photo Album', 'Canvas Photo Album', 'Digital Photo Album',
            'Photo Canvas Print', 'Metal Photo Print', 'Acrylic Photo Print',
            'Photo Keychain', 'Photo Mug', 'Photo Pillow', 'Photo Calendar',
            'Photo Puzzle', 'Photo Mouse Pad', 'Photo Phone Case',
        ];

        $name = fake()->randomElement($productNames).' '.fake()->randomElement(['Deluxe', 'Premium', 'Standard', 'Classic', 'Modern']);
        $price = fake()->randomFloat(2, 30, 800);

        return [
            'name' => $name,
            'sku' => 'PRD-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => fake()->sentence(10),
            'price' => $price,
            'base_price' => $price * 0.7,
            'is_active' => fake()->boolean(90),
        ];
    }
}
