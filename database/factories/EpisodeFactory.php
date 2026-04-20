<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EpisodeType;
use App\Models\Episode;
use App\Models\Show;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Episode>
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
            'type' => EpisodeType::Regular,
            'airdate' => fake()->date(),
            'airtime' => fake()->time('H:i'),
            'runtime' => fake()->randomElement([30, 45, 60, 90]),
            'rating' => ['average' => fake()->randomFloat(1, 5, 10)],
            'image' => null,
            'summary' => '<p>'.fake()->paragraph().'</p>',
        ];
    }

    /**
     * Indicate that the episode is a significant special.
     */
    public function special(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => EpisodeType::SignificantSpecial,
        ]);
    }

    /**
     * Indicate that the episode is an insignificant special.
     */
    public function insignificant(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => EpisodeType::InsignificantSpecial,
        ]);
    }
}
