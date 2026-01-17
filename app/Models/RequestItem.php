<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RequestItem extends Model
{
    /** @use HasFactory<\Database\Factories\RequestItemFactory> */
    use HasFactory;

    protected $guarded = [];

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    public function requestable(): MorphTo
    {
        return $this->morphTo();
    }
}
