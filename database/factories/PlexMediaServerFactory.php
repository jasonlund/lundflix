<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PlexMediaServer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PlexMediaServer>
 */
class PlexMediaServerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $domain = fake()->domainName();

        return [
            'name' => fake()->company().' Server',
            'client_identifier' => fake()->uuid(),
            'access_token' => 'fake-token-'.Str::random(20),
            'owned' => fake()->boolean(),
            'is_online' => true,
            'visible' => false,
            'poll_recently_added' => false,
            'uri' => 'http://'.$domain.':32400',
            'connections' => [
                ['uri' => 'http://'.fake()->localIpv4().':32400', 'local' => true],
                ['uri' => 'http://'.$domain.':32400', 'local' => false],
            ],
            'source_title' => fake()->userName(),
            'owner_thumb' => 'https://plex.tv/users/'.fake()->randomNumber(5).'/avatar',
            'owner_id' => (string) fake()->randomNumber(8),
            'product_version' => fake()->numerify('#.##.#.####'),
            'platform' => fake()->randomElement(['Linux', 'Windows', 'macOS']),
            'platform_version' => fake()->numerify('##.##'),
            'plex_last_seen_at' => now()->subMinutes(fake()->numberBetween(1, 120)),
            'last_seen_at' => now(),
        ];
    }

    public function pollRecentlyAdded(): static
    {
        return $this->state([
            'poll_recently_added' => true,
        ]);
    }

    /**
     * Indicate that the server is offline.
     */
    public function offline(): static
    {
        return $this->state([
            'is_online' => false,
            'last_seen_at' => now()->subHours(2),
        ]);
    }
}
