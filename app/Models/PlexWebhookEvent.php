<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlexWebhookEvent extends Model
{
    /** @use HasFactory<\Database\Factories\PlexWebhookEventFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'year' => 'integer',
            'season' => 'integer',
            'episode_number' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUnprocessed(Builder $query): Builder
    {
        return $query->whereNull('processed_at');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForServer(Builder $query, string $uuid): Builder
    {
        return $query->where('server_uuid', $uuid);
    }
}
