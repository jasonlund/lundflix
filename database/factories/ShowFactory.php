<?php

namespace Database\Factories;

use App\Enums\Language;
use App\Enums\ShowStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Show>
 */
class ShowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tvmaze_id' => fake()->unique()->numberBetween(1, 100000),
            'imdb_id' => 'tt'.fake()->unique()->numerify('#######'),
            'thetvdb_id' => fake()->unique()->numberBetween(100000, 999999),
            'tmdb_id' => fake()->unique()->numberBetween(1, 999999),
            'name' => fake()->sentence(3),
            'type' => fake()->randomElement(['Scripted', 'Animation', 'Reality', 'Documentary', 'Talk Show']),
            'language' => fake()->randomElement(Language::cases())->getLabel(),
            'genres' => fake()->randomElements(['Drama', 'Comedy', 'Action', 'Thriller', 'Sci-Fi', 'Horror'], 2),
            'status' => fake()->randomElement(ShowStatus::cases())->value,
            'runtime' => fake()->randomElement([30, 45, 60]),
            'average_runtime' => null,
            'premiered' => fake()->date(),
            'ended' => null,
            'schedule' => ['time' => '21:00', 'days' => ['Monday']],
            'network' => ['id' => 1, 'name' => 'NBC', 'country' => ['name' => 'United States']],
            'web_channel' => null,
        ];
    }

    public function appleTvPlus(): static
    {
        return $this->state(fn (array $attributes) => [
            'network' => null,
            'web_channel' => ['id' => 310, 'name' => 'Apple TV+'],
        ]);
    }

    public function paramountPlus(): static
    {
        return $this->state(fn (array $attributes) => [
            'network' => null,
            'web_channel' => ['id' => 107, 'name' => 'Paramount+'],
        ]);
    }

    public function hboMax(): static
    {
        return $this->state(fn (array $attributes) => [
            'network' => null,
            'web_channel' => ['id' => 329, 'name' => 'Max'],
        ]);
    }

    public function withTmdbData(): static
    {
        return $this->state(fn (array $attributes) => [
            'tmdb_id' => fake()->unique()->numberBetween(1, 999999),
            'tmdb_synced_at' => now(),
        ]);
    }
}
