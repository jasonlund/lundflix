<?php

namespace App\Models;

use App\Casts\SpokenLanguages;
use App\Enums\Language;
use App\Enums\MediaType;
use App\Enums\MovieStatus;
use App\Enums\TMDBReleaseType;
use App\Models\Concerns\HasArtwork;
use Carbon\Carbon;
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
 * @property list<array{iso_3166_1: string, release_dates: list<array{type: int, release_date: string, certification: string}>}>|null $release_dates
 */
class Movie extends Model
{
    /** @use HasFactory<\Database\Factories\MovieFactory> */
    use HasArtwork, HasFactory, Searchable;

    protected $guarded = [];

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
            'production_companies' => 'array',
            'spoken_languages' => SpokenLanguages::class,
            'alternative_titles' => 'array',
            'original_language' => Language::class,
            'budget' => 'integer',
            'revenue' => 'integer',
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

        if ($theatricalDate) {
            $hasKnownDigitalPhysical = $this->digital_release_date !== null
                || $this->hasAnyDateOfTypes($allDates, TMDBReleaseType::Digital, TMDBReleaseType::Physical);

            if (! $hasKnownDigitalPhysical && $theatricalDate->copy()->addDays(90)->isPast()) {
                return MovieStatus::Released;
            }

            return MovieStatus::InTheaters;
        }

        $premiereDate = $this->earliestPastDateOfTypes($allDates, TMDBReleaseType::Premiere);

        if ($premiereDate) {
            $hasFutureTheatrical = $this->earliestFutureDateOfTypes($allDates, TMDBReleaseType::Theatrical, TMDBReleaseType::TheatricalLimited);

            if (! $hasFutureTheatrical && $premiereDate->copy()->addDays(90)->isPast()) {
                return MovieStatus::Released;
            }

            return MovieStatus::FestivalRelease;
        }

        if ($this->earliestFutureDateOfTypes($allDates, TMDBReleaseType::Theatrical, TMDBReleaseType::TheatricalLimited)) {
            return MovieStatus::Upcoming;
        }

        if ($this->release_date?->isFuture()) {
            return MovieStatus::Upcoming;
        }

        return MovieStatus::Released;
    }

    /**
     * @return Collection<int, array{type: int, release_date: string, certification: string}>
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
            'id' => $this->id,
            'imdb_id' => $this->imdb_id,
            'title' => $this->title,
            'year' => $this->year,
            'num_votes' => $this->num_votes,
            'original_language' => $this->original_language?->value, // @phpstan-ignore property.nonObject (casted to Language enum)
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

    protected function artworkExternalIdValue(): string|int|null
    {
        return $this->imdb_id;
    }

    protected function artworkMediableType(): string
    {
        return 'movie';
    }
}
