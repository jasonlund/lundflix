<?php

namespace App\Models;

use App\Enums\RequestItemStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RequestItem extends Model
{
    /** @use HasFactory<\Database\Factories\RequestItemFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => RequestItemStatus::class,
            'actioned_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    public function requestable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actionedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actioned_by');
    }

    public function markFulfilled(?int $userId = null): bool
    {
        return $this->update([
            'status' => RequestItemStatus::Fulfilled,
            'actioned_by' => $userId ?? auth()->id(),
            'actioned_at' => now(),
        ]);
    }

    public function markRejected(): bool
    {
        return $this->update([
            'status' => RequestItemStatus::Rejected,
            'actioned_by' => null,
            'actioned_at' => null,
        ]);
    }

    public function markNotFound(): bool
    {
        return $this->update([
            'status' => RequestItemStatus::NotFound,
            'actioned_by' => null,
            'actioned_at' => null,
        ]);
    }

    public function markPending(): bool
    {
        return $this->update([
            'status' => RequestItemStatus::Pending,
            'actioned_by' => null,
            'actioned_at' => null,
        ]);
    }

    public function scopeFulfilled($query)
    {
        return $query->where('status', RequestItemStatus::Fulfilled);
    }

    public function scopePending($query)
    {
        return $query->where('status', RequestItemStatus::Pending);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', RequestItemStatus::Rejected);
    }

    public function scopeNotFound($query)
    {
        return $query->where('status', RequestItemStatus::NotFound);
    }
}
