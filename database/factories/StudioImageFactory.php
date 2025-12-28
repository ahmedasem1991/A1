<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StudioImage>
 */
class StudioImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sizes = ['4x6', '5x7', '8x10', '11x14', '16x20', '20x24', 'A4', 'A3'];
        $imageCount = fake()->randomElement([1, 2, 4, 6, 8, 10, 12, 24]);
        $basePrice = fake()->randomFloat(2, 50, 500);

        return [
            'image_size' => fake()->randomElement($sizes),
            'image_count' => $imageCount,
            'price' => $basePrice,
            'instant_price' => fake()->boolean(70) ? fake()->randomFloat(2, 20, 100) : 0,
            'soft_copy_price' => fake()->boolean(60) ? fake()->randomFloat(2, 10, 50) : 0,
            'name_price' => fake()->boolean(80) ? 5.00 : fake()->randomFloat(2, 3, 10),
        ];
    }
}
