<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\LanguageFromCode;
use App\Casts\LanguageFromName;
use App\Enums\NetworkLogo;
use App\Enums\ShowStatus;
use App\Enums\StreamingLogo;
use App\Models\Concerns\HasArtwork;
use App\Models\Concerns\HasObfuscatedId;
use App\Support\AirDateTime;
use Database\Factories\ShowFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Laravel\Scout\Searchable;

/**
 * @property string $name
 */
class Show extends Model
{
    /** @use HasFactory<ShowFactory> */
    use HasArtwork, HasFactory, HasObfuscatedId, Searchable {
        HasObfuscatedId::resolveRouteBindingQuery as resolveSqidRouteBindingQuery;
    }

    public const AMBIGUOUS_NAMES_CACHE_KEY = 'shows.ambiguous_names';

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
            'tmdb_id' => 'integer',
            'tmdb_synced_at' => 'datetime',
            'original_language' => LanguageFromCode::class,
            'content_ratings' => 'array',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $array = [
            'id' => (string) $this->id,
            'imdb_id' => (string) $this->imdb_id,
            'name' => (string) $this->getRawOriginal('name'),
            'num_votes' => (int) $this->num_votes,
            'language' => $this->language ? (string) $this->language->value : null, // @phpstan-ignore property.nonObject (casted to Language enum)
        ];

        // `country` and `year` are computed fields for the Typesense index.
        // The database Scout driver used locally treats every key as a real
        // column, so we omit them unless an index-backed engine is configured.
        if (config('scout.driver') !== 'database') {
            $array['country'] = $this->displayCountryCode();
            $array['year'] = $this->premiered ? (string) $this->premiered->year : null; // @phpstan-ignore property.nonObject (casted to date)
        }

        return $array;
    }

    /**
     * @return HasMany<Episode, $this>
     */
    public function episodes(): HasMany
    {
        return $this->hasMany(Episode::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        if ($field === null && is_string($value) && str_starts_with($value, 'tt')) {
            return $query->where('imdb_id', $value);
        }

        return $this->resolveSqidRouteBindingQuery($query, $value, $field);
    }

    /**
     * Retrieve the model for a bound value with eager-loaded episodes.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->resolveRouteBindingQuery(
            $this->query()->with('episodes'), $value, $field
        )->first();
    }

    /**
     * Get the most recent season (currently airing, or most recently completed).
     */
    protected function mostRecentSeason(): Attribute
    {
        return Attribute::get(function (): ?int {
            $cutoff = AirDateTime::effectiveAirDateCutoff($this->web_channel, $this->network)->format('Y-m-d'); // @phpstan-ignore argument.type, argument.type (casted to array)

            // Priority: 1) Currently airing (has past AND future episodes)
            //           2) Completed (has only past episodes)
            //           3) Future-only (upcoming season)
            // Within each tier, prefer the highest season number.
            $result = $this->episodes()
                ->selectRaw('season')
                ->selectRaw('SUM(CASE WHEN DATE(airdate) <= ? THEN 1 ELSE 0 END) as past_count', [$cutoff])
                ->selectRaw('SUM(CASE WHEN DATE(airdate) > ? THEN 1 ELSE 0 END) as future_count', [$cutoff])
                ->groupBy('season')
                ->orderByRaw('(SUM(CASE WHEN DATE(airdate) <= ? THEN 1 ELSE 0 END) > 0 AND SUM(CASE WHEN DATE(airdate) > ? THEN 1 ELSE 0 END) > 0) DESC', [$cutoff, $cutoff])
                ->orderByRaw('(SUM(CASE WHEN DATE(airdate) <= ? THEN 1 ELSE 0 END) > 0) DESC', [$cutoff])
                ->orderByDesc('season')
                ->first();

            return $result?->season;
        })->shouldCache();
    }

    /**
     * Render the show name with a country-code suffix when the base name
     * collides with another show in a different country. Skips appending
     * when the name already contains the code as a whole word.
     */
    protected function name(): Attribute
    {
        return Attribute::get(function (mixed $value): string {
            $name = (string) $value;
            $code = $this->displayCountryCode();

            if ($code === null) {
                return $name;
            }

            if (! static::ambiguousNames()->contains(mb_strtolower(trim($name)))) {
                return $name;
            }

            if (preg_match('/\b'.preg_quote($code, '/').'\b/i', $name) === 1) {
                return $name;
            }

            return $name.' '.$code;
        })->shouldCache();
    }

    public function displayCountryCode(): ?string
    {
        /** @var array<string, mixed>|null $network */
        $network = $this->network;
        /** @var array<string, mixed>|null $webChannel */
        $webChannel = $this->web_channel;

        $code = $network['country']['code']
            ?? $webChannel['country']['code']
            ?? null;

        if (! is_string($code) || $code === '') {
            return null;
        }

        return $code === 'GB' ? 'UK' : $code;
    }

    /**
     * Lowercased base names that appear under more than one country. Cached
     * forever; refreshed by the TVMaze sync commands via recomputeAmbiguousNames().
     *
     * @return Collection<int, string>
     */
    public static function ambiguousNames(): Collection
    {
        /** @var Collection<int, string> $names */
        $names = Cache::rememberForever(
            self::AMBIGUOUS_NAMES_CACHE_KEY,
            fn (): Collection => static::computeAmbiguousNames(),
        );

        return $names;
    }

    /**
     * Recompute the ambiguous-name set and store it in cache.
     *
     * @return Collection<int, string>
     */
    public static function recomputeAmbiguousNames(): Collection
    {
        $names = static::computeAmbiguousNames();
        Cache::forever(self::AMBIGUOUS_NAMES_CACHE_KEY, $names);

        return $names;
    }

    /**
     * @return Collection<int, string>
     */
    protected static function computeAmbiguousNames(): Collection
    {
        /** @var Collection<int, string> $names */
        $names = static::query()
            ->selectRaw('LOWER(TRIM(name)) AS base_name')
            ->whereRaw("COALESCE(json_extract(network, '$.country.code'), json_extract(web_channel, '$.country.code')) IS NOT NULL")
            ->groupBy('base_name')
            ->havingRaw("COUNT(DISTINCT COALESCE(json_extract(network, '$.country.code'), json_extract(web_channel, '$.country.code'))) > 1")
            ->pluck('base_name');

        return $names;
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

    public function contentRating(): ?string
    {
        /** @var array{rating: string, iso_3166_1: string}|null $usEntry */
        $usEntry = collect($this->content_ratings ?? [])
            ->firstWhere('iso_3166_1', 'US');

        return $usEntry['rating'] ?? null;
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

    /**
     * @return MorphMany<Subscription, $this>
     */
    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'subscribable');
    }

    protected function artworkExternalIdValue(): string|int|null
    {
        return $this->tmdb_id;
    }

    protected function artworkMediableType(): string
    {
        return 'show';
    }
}
