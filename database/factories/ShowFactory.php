<?php

namespace Database\Factories;

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
            'name' => fake()->sentence(3),
            'type' => fake()->randomElement(['Scripted', 'Animation', 'Reality', 'Documentary', 'Talk Show']),
            'language' => 'English',
            'genres' => fake()->randomElements(['Drama', 'Comedy', 'Action', 'Thriller', 'Sci-Fi', 'Horror'], 2),
            'status' => fake()->randomElement(['Running', 'Ended', 'To Be Determined']),
            'runtime' => fake()->randomElement([30, 45, 60]),
            'premiered' => fake()->date(),
            'ended' => null,
            'schedule' => ['time' => '21:00', 'days' => ['Monday']],
            'network' => ['id' => 1, 'name' => 'NBC', 'country' => ['name' => 'United States']],
            'web_channel' => null,
        ];
    }
}
