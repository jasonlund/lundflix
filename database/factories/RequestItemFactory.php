<?php

namespace Database\Factories;

use App\Models\Movie;
use App\Models\Request;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RequestItem>
 */
class RequestItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_id' => Request::factory(),
            'requestable_type' => Movie::class,
            'requestable_id' => Movie::factory(),
        ];
    }

    /**
     * Create an item for a specific requestable.
     */
    public function forRequestable(mixed $requestable): static
    {
        return $this->state(fn (array $attributes) => [
            'requestable_type' => $requestable::class,
            'requestable_id' => $requestable->id,
        ]);
    }
}
