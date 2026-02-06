<?php

namespace App\Models;

use App\Enums\RequestItemStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Request extends Model
{
    /** @use HasFactory<\Database\Factories\RequestFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $with = ['items'];

    protected function status(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $totalItems = $this->items->count();

                // No items: fallback to 'pending'
                if ($totalItems === 0) {
                    return 'pending';
                }

                $fulfilledItems = $this->items
                    ->where('status', RequestItemStatus::Fulfilled)
                    ->count();

                // Calculate based solely on fulfillment (ignore rejected/not_found)
                if ($fulfilledItems === 0) {
                    return 'pending';
                }

                if ($fulfilledItems === $totalItems) {
                    return 'fulfilled';
                }

                return 'partially fulfilled';
            }
        );
    }

    protected function hasRejectedItems(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->items
                ->where('status', RequestItemStatus::Rejected)
                ->isNotEmpty()
        );
    }

    protected function hasNotFoundItems(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->items
                ->where('status', RequestItemStatus::NotFound)
                ->isNotEmpty()
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RequestItem::class);
    }

    public function markItemsAs(
        array $itemIds,
        RequestItemStatus $status,
        ?int $userId = null
    ): int {
        $updates = ['status' => $status];

        // Only track user/time for final statuses
        if (in_array($status, [
            RequestItemStatus::Fulfilled,
            RequestItemStatus::Rejected,
            RequestItemStatus::NotFound,
        ])) {
            $updates['actioned_by'] = $userId ?? auth()->id();
            $updates['actioned_at'] = now();
        } else {
            // Pending status clears tracking
            $updates['actioned_by'] = null;
            $updates['actioned_at'] = null;
        }

        $count = $this->items()
            ->whereIn('id', $itemIds)
            ->update($updates);

        $this->refresh();

        return $count;
    }

    public function markItemsFulfilled(array $itemIds, ?int $userId = null): int
    {
        return $this->markItemsAs($itemIds, RequestItemStatus::Fulfilled, $userId);
    }

    public function markItemsRejected(array $itemIds, ?int $userId = null): int
    {
        return $this->markItemsAs($itemIds, RequestItemStatus::Rejected, $userId);
    }

    public function markItemsNotFound(array $itemIds, ?int $userId = null): int
    {
        return $this->markItemsAs($itemIds, RequestItemStatus::NotFound, $userId);
    }

    public function markItemsPending(array $itemIds): int
    {
        return $this->markItemsAs($itemIds, RequestItemStatus::Pending);
    }

    public function markAllItemsFulfilled(?int $userId = null): int
    {
        return $this->markItemsAs(
            $this->items->pluck('id')->toArray(),
            RequestItemStatus::Fulfilled,
            $userId
        );
    }

    public function markAllItemsRejected(?int $userId = null): int
    {
        return $this->markItemsAs(
            $this->items->pluck('id')->toArray(),
            RequestItemStatus::Rejected,
            $userId
        );
    }

    public function markAllItemsNotFound(?int $userId = null): int
    {
        return $this->markItemsAs(
            $this->items->pluck('id')->toArray(),
            RequestItemStatus::NotFound,
            $userId
        );
    }

    public function markAllItemsPending(): int
    {
        return $this->markItemsAs(
            $this->items->pluck('id')->toArray(),
            RequestItemStatus::Pending
        );
    }
}
