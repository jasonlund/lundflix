<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Subscription extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fulfilled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subscribable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsToMany<Episode, $this>
     */
    public function processedEpisodes(): BelongsToMany
    {
        return $this->belongsToMany(Episode::class, 'subscription_episode')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->whereNull('fulfilled_at');
    }

    public function scopeForMovies($query)
    {
        return $query->where('subscribable_type', Movie::class);
    }

    public function scopeForShows($query)
    {
        return $query->where('subscribable_type', Show::class);
    }

    public function markFulfilled(): void
    {
        $this->fulfilled_at = now();
        $this->save();
    }
}
