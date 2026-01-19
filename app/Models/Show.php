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

            // Find season that is "currently airing" (has both past AND future episodes)
            $currentlyAiring = $this->episodes()
                ->selectRaw('season')
                ->groupBy('season')
                ->havingRaw('SUM(CASE WHEN airdate <= ? THEN 1 ELSE 0 END) > 0', [$today])
                ->havingRaw('SUM(CASE WHEN airdate > ? THEN 1 ELSE 0 END) > 0', [$today])
                ->orderByDesc('season')
                ->value('season');

            if ($currentlyAiring !== null) {
                return (int) $currentlyAiring;
            }

            // Fallback to highest season with at least one aired episode
            $highestAired = $this->episodes()
                ->where('airdate', '<=', $today)
                ->max('season');

            if ($highestAired !== null) {
                return (int) $highestAired;
            }

            // No aired episodes - show the first season (for upcoming shows)
            $firstSeason = $this->episodes()->min('season');

            return $firstSeason !== null ? (int) $firstSeason : null;
        });
    }
}
