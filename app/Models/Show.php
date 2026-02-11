<?php

namespace App\Models;

use App\Casts\LanguageFromName;
use App\Enums\NetworkLogo;
use App\Enums\ShowStatus;
use App\Enums\StreamingLogo;
use App\Models\Concerns\HasArtwork;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;

class Show extends Model
{
    /** @use HasFactory<\Database\Factories\ShowFactory> */
    use HasArtwork, HasFactory, Searchable;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'genres' => 'array',
            'schedule' => 'array',
            'network' => 'array',
            'web_channel' => 'array',
            'premiered' => 'date',
            'ended' => 'date',
            'num_votes' => 'integer',
            'status' => ShowStatus::class,
            'language' => LanguageFromName::class,
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
     * Retrieve the model for a bound value with eager-loaded episodes.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->query()
            ->with('episodes')
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
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

    /**
     * @return MorphMany<Media, $this>
     */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    /**
     * @return array{value: int, approximate: bool}|null
     */
    public function displayRuntime(): ?array
    {
        if ($this->runtime !== null) {
            return ['value' => $this->runtime, 'approximate' => false];
        }

        if ($this->average_runtime !== null) {
            return ['value' => $this->average_runtime, 'approximate' => true];
        }

        return null;
    }

    public function networkLogoUrl(): ?string
    {
        /** @var array<string, mixed>|null $network */
        $network = $this->network;

        if (isset($network['id'])) {
            return NetworkLogo::tryFrom($network['id'])?->url();
        }

        return null;
    }

    public function streamingLogoUrl(): ?string
    {
        /** @var array<string, mixed>|null $webChannel */
        $webChannel = $this->web_channel;

        if (isset($webChannel['id'])) {
            return StreamingLogo::tryFrom($webChannel['id'])?->url();
        }

        return null;
    }

    protected function artworkExternalIdValue(): string|int|null
    {
        return $this->thetvdb_id;
    }

    protected function artworkMediableType(): string
    {
        return 'show';
    }
}
