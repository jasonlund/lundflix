<?php

namespace Database\Factories;

use App\Models\Movie;
use App\Models\Show;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Media>
 */
class MediaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['movieposter', 'moviebackground', 'hdmovielogo', 'moviedisc', 'moviebanner'];

        return [
            'mediable_type' => Movie::class,
            'mediable_id' => Movie::factory(),
            'fanart_id' => $this->faker->unique()->numerify('######'),
            'type' => $this->faker->randomElement($types),
            'url' => $this->faker->imageUrl(1000, 1500, 'movies'),
            'path' => null,
            'lang' => $this->faker->randomElement(['en', 'de', 'fr', 'es', null]),
            'likes' => $this->faker->numberBetween(0, 500),
            'season' => null,
            'disc' => null,
            'disc_type' => null,
        ];
    }

    public function forShow(): static
    {
        $types = ['tvposter', 'showbackground', 'hdtvlogo', 'seasonposter'];

        return $this->state(fn (array $attributes) => [
            'mediable_type' => Show::class,
            'mediable_id' => Show::factory(),
            'type' => $this->faker->randomElement($types),
        ]);
    }

    public function withSeason(int $season): static
    {
        return $this->state(fn (array $attributes) => [
            'season' => $season,
        ]);
    }
}
