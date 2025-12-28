<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ImageCard>
 */
class ImageCardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cardSizes = ['Business Card', 'Postcard', 'Greeting Card', 'ID Card', 'Credit Card Size'];
        $basePrice = fake()->randomFloat(2, 20, 200);

        return [
            'card_size' => fake()->randomElement($cardSizes),
            'price' => $basePrice,
            'instant_price' => fake()->boolean(60) ? fake()->randomFloat(2, 10, 50) : 0,
        ];
    }
}
