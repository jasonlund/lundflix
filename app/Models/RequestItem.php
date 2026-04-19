<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RequestItemStatus;
use Database\Factories\RequestItemFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RequestItem extends Model
{
    /** @use HasFactory<RequestItemFactory> */
    use HasFactory;

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

    #[Scope]
    protected function fulfilled($query)
    {
        return $query->where('status', RequestItemStatus::Fulfilled);
    }

    #[Scope]
    protected function pending($query)
    {
        return $query->where('status', RequestItemStatus::Pending);
    }

    #[Scope]
    protected function rejected($query)
    {
        return $query->where('status', RequestItemStatus::Rejected);
    }

    #[Scope]
    protected function notFound($query)
    {
        return $query->where('status', RequestItemStatus::NotFound);
    }
}
