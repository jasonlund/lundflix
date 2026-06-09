<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubscriptionMode;
use App\Models\Movie;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subscribable_type' => Movie::class,
            'subscribable_id' => Movie::factory(),
            'mode' => SubscriptionMode::Download,
        ];
    }

    /**
     * Create a subscription for a specific subscribable.
     */
    public function forSubscribable(mixed $subscribable): static
    {
        return $this->state(fn (array $attributes): array => [
            'subscribable_type' => $subscribable::class,
            'subscribable_id' => $subscribable->id,
        ]);
    }

    /**
     * Create a notification-only subscription that never auto-downloads.
     */
    public function notifyOnly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'mode' => SubscriptionMode::Notify,
        ]);
    }
}
