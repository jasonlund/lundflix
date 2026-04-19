<?php

namespace App\Models;

use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
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

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->whereNull('fulfilled_at');
    }

    #[Scope]
    protected function forMovies(Builder $query): Builder
    {
        return $query->where('subscribable_type', Movie::class);
    }

    #[Scope]
    protected function forShows(Builder $query): Builder
    {
        return $query->where('subscribable_type', Show::class);
    }

    public function markFulfilled(): void
    {
        $this->fulfilled_at = now();
        $this->save();
    }
}
