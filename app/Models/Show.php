<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Show extends Model
{
    /** @use HasFactory<\Database\Factories\ShowFactory> */
    use HasFactory, Searchable;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'genres' => 'array',
            'schedule' => 'array',
            'rating' => 'array',
            'network' => 'array',
            'web_channel' => 'array',
            'externals' => 'array',
            'image' => 'array',
            'premiered' => 'date',
            'ended' => 'date',
            'num_votes' => 'integer',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'imdb_id' => $this->imdb_id,
            'name' => $this->name,
            'year' => $this->premiered?->year, // @phpstan-ignore property.nonObject (casted to Carbon)
            'num_votes' => $this->num_votes,
        ];
    }

    /**
     * @return HasMany<Episode, $this>
     */
    public function episodes(): HasMany
    {
        return $this->hasMany(Episode::class);
    }

    /**
     * Get the most recent season (currently airing, or most recently completed).
     */
    protected function mostRecentSeason(): Attribute
    {
        return Attribute::get(function (): ?int {
            $today = now()->startOfDay();

            // Priority: 1) Currently airing (has past AND future episodes)
            //           2) Completed (has only past episodes)
            //           3) Future-only (upcoming season)
            // Within each tier, prefer the highest season number.
            $result = $this->episodes()
                ->selectRaw('season')
                ->selectRaw('SUM(CASE WHEN airdate <= ? THEN 1 ELSE 0 END) as past_count', [$today])
                ->selectRaw('SUM(CASE WHEN airdate > ? THEN 1 ELSE 0 END) as future_count', [$today])
                ->groupBy('season')
                ->orderByRaw('(SUM(CASE WHEN airdate <= ? THEN 1 ELSE 0 END) > 0 AND SUM(CASE WHEN airdate > ? THEN 1 ELSE 0 END) > 0) DESC', [$today, $today])
                ->orderByRaw('(SUM(CASE WHEN airdate <= ? THEN 1 ELSE 0 END) > 0) DESC', [$today])
                ->orderByDesc('season')
                ->first();

            return $result?->season;
        })->shouldCache();
    }
}
