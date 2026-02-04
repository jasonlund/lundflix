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
}
