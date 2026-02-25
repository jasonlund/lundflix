<?php

namespace App\Models;

use App\Enums\RequestItemStatus;
use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Request extends Model
{
    /** @use HasFactory<\Database\Factories\RequestFactory> */
    use HasFactory;

    protected $with = ['items'];

    protected function status(): Attribute
    {
        return Attribute::make(
            get: function (): RequestStatus {
                $totalItems = $this->items->count();

                if ($totalItems === 0) {
                    return RequestStatus::Pending;
                }

                $fulfilledItems = $this->items
                    ->where('status', RequestItemStatus::Fulfilled)
                    ->count();

                if ($fulfilledItems === $totalItems) {
                    return RequestStatus::Fulfilled;
                }

                if ($fulfilledItems > 0) {
                    return RequestStatus::PartiallyFulfilled;
                }

                // All items actioned (rejected/not_found) with zero fulfilled
                $actionedItems = $this->items
                    ->whereIn('status', [RequestItemStatus::Rejected, RequestItemStatus::NotFound])
                    ->count();

                if ($actionedItems === $totalItems) {
                    return RequestStatus::Rejected;
                }

                return RequestStatus::Pending;
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
}
