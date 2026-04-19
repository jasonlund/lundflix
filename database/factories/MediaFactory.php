<?php

namespace Database\Factories;

use App\Enums\ArtworkType;
use App\Models\Media;
use App\Models\Movie;
use App\Models\Show;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mediable_type' => Movie::class,
            'mediable_id' => Movie::factory(),
            'file_path' => '/'.$this->faker->unique()->md5().'.jpg',
            'type' => $this->faker->randomElement(ArtworkType::cases())->value,
            'path' => null,
            'lang' => $this->faker->randomElement(['en', 'de', 'fr', 'es', null]),
            'vote_average' => $this->faker->randomFloat(3, 0, 10),
            'vote_count' => $this->faker->numberBetween(0, 500),
            'width' => $this->faker->randomElement([500, 780, 1280, 1920]),
            'height' => $this->faker->randomElement([750, 1170, 720, 1080]),
            'season' => null,
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => true,
        ]);
    }

    public function forShow(): static
    {
        return $this->state(fn (array $attributes): array => [
            'mediable_type' => Show::class,
            'mediable_id' => Show::factory(),
        ]);
    }

    public function withSeason(int $season): static
    {
        return $this->state(fn (array $attributes): array => [
            'season' => $season,
        ]);
    }
}
