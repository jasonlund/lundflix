<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\LanguageFromCode;
use App\Enums\MediaType;
use App\Enums\MovieStatus;
use App\Enums\TMDBReleaseType;
use App\Models\Concerns\HasArtwork;
use App\Models\Concerns\HasObfuscatedId;
use Carbon\Carbon;
use Database\Factories\MovieFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Laravel\Scout\Searchable;

/**
 * @property \Illuminate\Support\Carbon|null $release_date
 * @property \Illuminate\Support\Carbon|null $digital_release_date
 * @property list<string>|null $origin_country
 * @property list<array{iso_3166_1: string, release_dates: list<array{type: int, release_date: string, certification: string, note?: string, iso_639_1?: string, descriptors?: list<string>}>}>|null $release_dates
 */
class Movie extends Model
{
    /** @use HasFactory<MovieFactory> */
    use HasArtwork, HasFactory, HasObfuscatedId, Searchable {
        HasObfuscatedId::resolveRouteBindingQuery as resolveSqidRouteBindingQuery;
    }

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'runtime' => 'integer',
            'num_votes' => 'integer',
            'genres' => 'array',
            'tmdb_id' => 'integer',
            'release_date' => 'date',
            'digital_release_date' => 'date',
            'original_language' => LanguageFromCode::class,
            'origin_country' => 'array',
            'release_dates' => 'array',
            'tmdb_synced_at' => 'datetime',
        ];
    }

    /**
     * @return Attribute<MovieStatus|null, never>
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): ?MovieStatus {
                if ($value === null) {
                    return null;
                }

                return match ($value) {
                    'Canceled' => MovieStatus::Canceled,
                    'Post Production' => MovieStatus::PostProduction,
                    'In Production' => MovieStatus::InProduction,
                    'Planned' => MovieStatus::Planned,
                    'Rumored' => MovieStatus::Rumored,
                    'Released' => $this->resolveReleasedStatus(),
                    default => null,
                };
            },
        )->shouldCache();
    }

    private function resolveReleasedStatus(): MovieStatus
    {
        $allDates = $this->allReleaseDates();

        if ($this->digital_release_date?->isPast()
            || $this->earliestPastDateOfTypes($allDates, TMDBReleaseType::Digital, TMDBReleaseType::Physical)) {
            return MovieStatus::Released;
        }

        $theatricalDate = $this->earliestPastDateOfTypes($allDates, TMDBReleaseType::Theatrical, TMDBReleaseType::TheatricalLimited);

        if ($theatricalDate instanceof Carbon) {
            $hasKnownDigitalPhysical = $this->digital_release_date !== null
                || $this->hasAnyDateOfTypes($allDates, TMDBReleaseType::Digital, TMDBReleaseType::Physical);

            if (! $hasKnownDigitalPhysical && $theatricalDate->copy()->addDays(90)->isPast()) {
                return MovieStatus::Released;
            }

            return MovieStatus::InTheaters;
        }

        $premiereDate = $this->earliestPastDateOfTypes($allDates, TMDBReleaseType::Premiere);

        if ($premiereDate instanceof Carbon) {
            $hasFutureTheatrical = $this->earliestFutureDateOfTypes($allDates, TMDBReleaseType::Theatrical, TMDBReleaseType::TheatricalLimited);

            if (! $hasFutureTheatrical && $premiereDate->copy()->addDays(90)->isPast()) {
                return MovieStatus::Released;
            }

            return MovieStatus::FestivalRelease;
        }

        if ($this->earliestFutureDateOfTypes($allDates, TMDBReleaseType::Theatrical, TMDBReleaseType::TheatricalLimited) instanceof Carbon) {
            return MovieStatus::Upcoming;
        }

        if ($this->release_date?->isFuture()) {
            return MovieStatus::Upcoming;
        }

        return MovieStatus::Released;
    }

    /**
     * @return Collection<int, array{type: int, release_date: string, certification: string, note?: string, iso_639_1?: string, descriptors?: list<string>}>
     */
    private function allReleaseDates(): Collection
    {
        if (empty($this->release_dates)) {
            return collect();
        }

        return collect($this->release_dates)
            ->flatMap(fn (array $country): array => $country['release_dates']);
    }

    private function hasAnyDateOfTypes(Collection $dates, TMDBReleaseType ...$types): bool
    {
        $typeValues = array_map(fn (TMDBReleaseType $t): int => $t->value, $types);

        return $dates
            ->contains(fn (array $rd): bool => in_array($rd['type'], $typeValues));
    }

    private function earliestPastDateOfTypes(Collection $dates, TMDBReleaseType ...$types): ?Carbon
    {
        $typeValues = array_map(fn (TMDBReleaseType $t): int => $t->value, $types);

        return $dates
            ->filter(fn (array $rd): bool => in_array($rd['type'], $typeValues))
            ->map(function (array $rd): ?Carbon {
                $date = $rd['release_date'] ?? null;

                if (empty($date)) {
                    return null;
                }

                return Carbon::parse($date);
            })
            ->filter()
            ->filter(fn (Carbon $date): bool => $date->isPast())
            ->sortBy(fn (Carbon $date): int => $date->timestamp)
            ->first();
    }

    private function earliestFutureDateOfTypes(Collection $dates, TMDBReleaseType ...$types): ?Carbon
    {
        $typeValues = array_map(fn (TMDBReleaseType $t): int => $t->value, $types);

        return $dates
            ->filter(fn (array $rd): bool => in_array($rd['type'], $typeValues))
            ->map(function (array $rd): ?Carbon {
                $date = $rd['release_date'] ?? null;

                if (empty($date)) {
                    return null;
                }

                return Carbon::parse($date);
            })
            ->filter()
            ->filter(fn (Carbon $date): bool => $date->isFuture())
            ->sortBy(fn (Carbon $date): int => $date->timestamp)
            ->first();
    }

    public function meaningfulReleases(): Collection
    {
        if (empty($this->release_dates)) {
            return collect();
        }

        $originCountries = collect($this->origin_country ?? []);

        return collect($this->release_dates)
            ->flatMap(fn (array $country): array => array_map(
                fn (array $rd): array => [...$rd, 'country' => $country['iso_3166_1']],
                $country['release_dates'],
            ))
            ->filter(fn (array $rd): bool => ! empty($rd['release_date']))
            ->groupBy('type')
            ->map(function (Collection $entries, int $type) use ($originCountries): ?array {
                $releaseType = TMDBReleaseType::tryFrom($type);
                if (! $releaseType) {
                    return null;
                }

                $isOrigin = fn (array $rd): bool => $originCountries->contains($rd['country']);

                if ($releaseType !== TMDBReleaseType::Premiere) {
                    $entries = $entries->filter(
                        fn (array $rd): bool => $isOrigin($rd) || $rd['country'] === 'US'
                    );

                    if ($entries->isEmpty()) {
                        return null;
                    }
                }

                $preferred = $releaseType === TMDBReleaseType::Premiere
                    ? $entries->sortBy(fn (array $rd): string => Carbon::parse($rd['release_date'])->toDateTimeString())
                        ->sortBy(fn (array $rd): int => $isOrigin($rd) ? 1 : 0)
                        ->first()
                    : $entries->sortBy(fn (array $rd): string => Carbon::parse($rd['release_date'])->toDateTimeString())
                        ->sortByDesc(fn (array $rd): int => $isOrigin($rd) ? 1 : 0)
                        ->first();

                if (! $preferred) {
                    return null;
                }

                return [
                    'type' => $releaseType,
                    'date' => Carbon::parse($preferred['release_date']),
                    'country' => $preferred['country'],
                    'certification' => $preferred['certification'] !== '' ? $preferred['certification'] : null,
                    'note' => ($preferred['note'] ?? '') !== '' ? $preferred['note'] : null,
                    'descriptors' => $preferred['descriptors'] ?? [],
                ];
            })
            ->filter()
            ->sortBy(fn (array $r): int => $r['type']->value)
            ->values();
    }

    public function releaseDatesByCountry(): Collection
    {
        if (empty($this->release_dates)) {
            return collect();
        }

        $originCountries = collect($this->origin_country ?? []);
        $showCountries = $originCountries->merge(
            $originCountries->contains('US') ? [] : ['US']
        )->unique();

        return collect($this->release_dates)
            ->filter(fn (array $country): bool => $showCountries->contains($country['iso_3166_1']))
            ->map(function (array $country): ?array {
                $releases = collect($country['release_dates'])
                    ->filter(fn (array $rd): bool => ! empty($rd['release_date']))
                    ->map(function (array $rd): ?array {
                        $type = TMDBReleaseType::tryFrom($rd['type']);
                        if (! $type) {
                            return null;
                        }

                        return [
                            'type' => $type,
                            'date' => Carbon::parse($rd['release_date']),
                            'certification' => $rd['certification'] !== '' ? $rd['certification'] : null,
                            'note' => (isset($rd['note']) && $rd['note'] !== '') ? $rd['note'] : null,
                            'descriptors' => $rd['descriptors'] ?? [],
                        ];
                    })
                    ->filter()
                    ->sortBy(fn (array $r): string => $r['date']->toDateTimeString())
                    ->unique(fn (array $r): int => $r['type']->value)
                    ->values()
                    ->all();

                if (empty($releases)) {
                    return null;
                }

                return [
                    'country' => $country['iso_3166_1'],
                    'countryName' => \Locale::getDisplayRegion("-{$country['iso_3166_1']}", 'en') ?: $country['iso_3166_1'],
                    'releases' => $releases,
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $c): bool => $originCountries->contains($c['country']))
            ->values();
    }

    public function contentRating(): ?string
    {
        $usEntry = collect($this->release_dates ?? [])
            ->firstWhere('iso_3166_1', 'US');

        if (! $usEntry) {
            return null;
        }

        $certification = collect($usEntry['release_dates'])
            ->sortBy(fn (array $rd): int => match ($rd['type']) {
                TMDBReleaseType::Theatrical->value => 0,
                TMDBReleaseType::TheatricalLimited->value => 1,
                TMDBReleaseType::Premiere->value => 2,
                default => 3,
            })
            ->pluck('certification')
            ->filter(fn (string $cert): bool => $cert !== '')
            ->first();

        return $certification ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->id,
            'imdb_id' => (string) $this->imdb_id,
            'title' => (string) $this->title,
            'year' => $this->year ? (int) $this->year : null,
            'num_votes' => (int) $this->num_votes,
            'original_language' => $this->original_language ? (string) $this->original_language->value : null, // @phpstan-ignore property.nonObject (casted to Language enum)
        ];
    }

    public function getMediaType(): MediaType
    {
        return MediaType::MOVIE;
    }

    /**
     * @return MorphMany<Media, $this>
     */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    /**
     * @return MorphMany<Subscription, $this>
     */
    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'subscribable');
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

    protected function artworkExternalIdValue(): string|int|null
    {
        return $this->tmdb_id;
    }

    protected function artworkMediableType(): string
    {
        return 'movie';
    }
}
