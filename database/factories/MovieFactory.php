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

    public function withTmdbData(): static
    {
        return $this->state(fn (array $attributes) => [
            'tmdb_id' => fake()->unique()->numberBetween(1, 999999),
            'release_date' => fake()->date(),
            'digital_release_date' => fake()->date(),
            'production_companies' => [
                ['id' => fake()->numberBetween(1, 9999), 'name' => fake()->company()],
            ],
            'spoken_languages' => [
                ['iso_639_1' => 'en', 'english_name' => 'English', 'name' => 'English'],
            ],
            'alternative_titles' => [
                ['iso_3166_1' => 'FR', 'title' => fake()->sentence(2), 'type' => ''],
            ],
            'original_language' => fake()->randomElement(['en', 'fr', 'es', 'de', 'ja', 'ko']),
            'tmdb_synced_at' => now(),
        ]);
    }
}
