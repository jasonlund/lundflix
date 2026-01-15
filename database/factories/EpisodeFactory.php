<?php

namespace Database\Factories;

use App\Models\Show;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Episode>
 */
class EpisodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'show_id' => Show::factory(),
            'tvmaze_id' => fake()->unique()->numberBetween(1, 999999),
            'season' => fake()->numberBetween(1, 10),
            'number' => fake()->numberBetween(1, 24),
            'name' => fake()->sentence(3),
            'type' => 'regular',
            'airdate' => fake()->date(),
            'airtime' => fake()->time('H:i'),
            'runtime' => fake()->randomElement([30, 45, 60, 90]),
            'rating' => ['average' => fake()->randomFloat(1, 5, 10)],
            'image' => null,
            'summary' => '<p>'.fake()->paragraph().'</p>',
        ];
    }
}
