<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = [
            ['name' => 'Photo Frames', 'description' => 'Various photo frames and holders'],
            ['name' => 'Albums', 'description' => 'Photo albums and scrapbooks'],
            ['name' => 'Canvas Prints', 'description' => 'Canvas printed photos'],
            ['name' => 'Accessories', 'description' => 'Photography accessories'],
            ['name' => 'Gift Items', 'description' => 'Photo-related gift items'],
        ];

        $category = fake()->unique()->randomElement($categories);

        return [
            'name' => $category['name'],
            'description' => $category['description'],
        ];
    }
}
