<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlexWebhookEvent>
 */
class PlexWebhookEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_uuid' => fake()->uuid(),
            'server_name' => 'Test Plex Server',
            'media_type' => 'movie',
            'title' => fake()->sentence(3),
            'year' => fake()->year(),
            'payload' => [],
        ];
    }

    public function movie(string $title = 'Test Movie', ?int $year = 2024): static
    {
        return $this->state([
            'media_type' => 'movie',
            'title' => $title,
            'year' => $year,
            'show_title' => null,
            'season' => null,
            'episode_number' => null,
        ]);
    }

    public function episode(string $showTitle = 'Test Show', int $season = 1, int $episodeNumber = 1, string $title = 'Pilot'): static
    {
        return $this->state([
            'media_type' => 'episode',
            'title' => $title,
            'year' => null,
            'show_title' => $showTitle,
            'season' => $season,
            'episode_number' => $episodeNumber,
        ]);
    }

    public function processed(): static
    {
        return $this->state([
            'processed_at' => now(),
        ]);
    }
}
