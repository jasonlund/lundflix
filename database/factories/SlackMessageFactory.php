<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SlackNotificationType;
use App\Models\SlackMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SlackMessage>
 */
class SlackMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel' => 'C'.fake()->bothify('##########'),
            'message_ts' => fake()->unique()->numerify('##########.######'),
            'type' => fake()->randomElement(SlackNotificationType::cases()),
            'content' => fake()->sentence(),
            'sent_at' => fake()->dateTimeThisMonth(),
        ];
    }
}
