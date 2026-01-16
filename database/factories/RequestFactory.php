<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Request>
 */
class RequestFactory extends Factory
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
            'status' => 'pending',
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the request has been fulfilled.
     */
    public function fulfilled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'fulfilled',
        ]);
    }

    /**
     * Indicate that the request has been rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
        ]);
    }
}
