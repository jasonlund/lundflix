<?php

namespace Database\Factories;

use App\Models\Movie;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
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
        ];
    }

    /**
     * Create a subscription for a specific subscribable.
     */
    public function forSubscribable(mixed $subscribable): static
    {
        return $this->state(fn (array $attributes) => [
            'subscribable_type' => $subscribable::class,
            'subscribable_id' => $subscribable->id,
        ]);
    }
}
