<?php

namespace Database\Factories;

use App\Enums\RequestItemStatus;
use App\Models\Movie;
use App\Models\Request;
use App\Models\User;
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
            'status' => RequestItemStatus::Pending,
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

    /**
     * Indicate that the request item is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RequestItemStatus::Pending,
            'actioned_by' => null,
            'actioned_at' => null,
        ]);
    }

    /**
     * Indicate that the request item has been fulfilled.
     */
    public function fulfilled(?int $userId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RequestItemStatus::Fulfilled,
            'actioned_by' => $userId ?? User::factory(),
            'actioned_at' => now(),
        ]);
    }

    /**
     * Indicate that the request item was fulfilled at a specific time.
     */
    public function fulfilledAt(\Carbon\Carbon|string $datetime, ?int $userId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RequestItemStatus::Fulfilled,
            'actioned_by' => $userId ?? User::factory(),
            'actioned_at' => $datetime,
        ]);
    }

    /**
     * Indicate that the request item has been rejected.
     */
    public function rejected(?int $userId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RequestItemStatus::Rejected,
            'actioned_by' => $userId ?? User::factory(),
            'actioned_at' => now(),
        ]);
    }

    /**
     * Indicate that the request item was not found.
     */
    public function notFound(?int $userId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RequestItemStatus::NotFound,
            'actioned_by' => $userId ?? User::factory(),
            'actioned_at' => now(),
        ]);
    }
}
