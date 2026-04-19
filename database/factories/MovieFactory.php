<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Language;
use App\Enums\MovieStatus;
use App\Models\Movie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Movie>
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
            'tmdb_id' => fake()->unique()->numberBetween(1, 999999),
            'title' => fake()->sentence(3),
            'year' => fake()->year(),
            'runtime' => fake()->numberBetween(80, 180),
            'genres' => fake()->randomElements(['Action', 'Comedy', 'Drama', 'Thriller', 'Romance'], fake()->numberBetween(1, 3)),
        ];
    }

    public function withTmdbData(): static
    {
        return $this->state(fn (array $attributes): array => [
            'tmdb_id' => fake()->unique()->numberBetween(1, 999999),
            'release_date' => fake()->date(),
            'digital_release_date' => fake()->date(),
            'original_language' => fake()->randomElement(Language::cases())->value,
            'original_title' => fake()->sentence(3),
            'status' => fake()->randomElement([
                MovieStatus::Released,
                MovieStatus::Canceled,
                MovieStatus::PostProduction,
                MovieStatus::InProduction,
                MovieStatus::Planned,
                MovieStatus::Rumored,
            ])->value,
            'origin_country' => ['US'],
            'release_dates' => [
                [
                    'iso_3166_1' => 'US',
                    'release_dates' => [
                        [
                            'type' => 3,
                            'release_date' => fake()->date('Y-m-d\TH:i:s.000\Z'),
                            'certification' => fake()->randomElement(['G', 'PG', 'PG-13', 'R']),
                            'note' => '',
                            'iso_639_1' => '',
                            'descriptors' => [],
                        ],
                    ],
                ],
            ],
            'tmdb_synced_at' => now(),
        ]);
    }
}
