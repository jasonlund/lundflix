<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Movie>
 */
class MovieFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'imdb_id' => 'tt'.fake()->unique()->numerify('#######'),
            'title' => fake()->sentence(3),
            'year' => fake()->year(),
            'runtime' => fake()->numberBetween(80, 180),
            'genres' => fake()->randomElements(['Action', 'Comedy', 'Drama', 'Thriller', 'Romance'], fake()->numberBetween(1, 3)),
        ];
    }
}
