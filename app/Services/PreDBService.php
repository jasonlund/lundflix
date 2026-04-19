<?php

namespace App\Services;

use App\Enums\ReleaseQuality;
use App\Exceptions\PreDBRateLimitExceededException;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class PreDBService
{
    private const RATE_LIMIT_KEY = 'predb-api';

    private const RATE_LIMIT_ATTEMPTS = 28;

    private const RATE_LIMIT_DECAY = 60;

    /**
     * Check if a movie has any non-nuked scene releases after excluding low-quality tags.
     */
    public function hasQualityRelease(Movie $movie): bool
    {
        $query = $this->buildQuery($movie);

        if ($query === null) {
            return false;
        }

        $results = $this->search($query);

        foreach ($results as $release) {
            if (($release['status'] ?? -1) === 1) {
                continue; // Skip nuked releases
            }

            return true;
        }

        return false;
    }

    /**
     * Find the highest quality non-nuked scene release for a movie.
     */
    public function highestQuality(Movie $movie): ?ReleaseQuality
    {
        $query = $this->buildQuery($movie);

        if ($query === null) {
            return null;
        }

        $results = $this->search($query);
        $highest = null;

        foreach ($results as $release) {
            if (($release['status'] ?? -1) === 1) {
                continue;
            }

            $quality = ReleaseQuality::fromReleaseName($release['release'] ?? '');

            if (! $quality instanceof ReleaseQuality) {
                continue;
            }

            if (! $highest instanceof ReleaseQuality || $quality->value > $highest->value) {
                $highest = $quality;
            }
        }

        return $highest;
    }

    /**
     * Search PreDB for releases matching a query, excluding low-quality tags.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws PreDBRateLimitExceededException
     */
    public function search(string $query, int $limit = 100): array
    {
        if (RateLimiter::tooManyAttempts(self::RATE_LIMIT_KEY, self::RATE_LIMIT_ATTEMPTS)) {
            throw new PreDBRateLimitExceededException;
        }

        RateLimiter::hit(self::RATE_LIMIT_KEY, self::RATE_LIMIT_DECAY);

        $response = $this->client()->get($this->baseUrl(), [
            'q' => $query,
            'tag' => implode(',', array_map(fn (string $tag): string => '-'.$tag, ReleaseQuality::excludedTags())),
            'limit' => $limit,
        ]);

        $response->throw();

        $data = $response->json();

        if (($data['status'] ?? '') !== 'success') {
            return [];
        }

        return $data['data'] ?? [];
    }

    /**
     * Build a dot-separated search query from a movie's title and year.
     *
     * Scene release names follow: Movie.Title.Year.Quality.Source.Codec-Group
     */
    public function buildQuery(Movie $movie): ?string
    {
        if (empty($movie->title)) {
            return null;
        }

        // Replace spaces with dots, strip characters that don't appear in scene names
        $title = preg_replace('/[^\w\s.-]/', '', (string) $movie->title);
        $title = (string) preg_replace('/\s+/', '.', trim((string) $title));

        if ($movie->year) {
            $title .= '.'.$movie->year;
        }

        return $title;
    }

    /**
     * Build a dot-separated search query from a show's name (no year).
     */
    public function buildShowQuery(Show $show): ?string
    {
        if (empty($show->name)) {
            return null;
        }

        $name = preg_replace('/[^\w\s.-]/', '', (string) $show->name);
        $name = (string) preg_replace('/\s+/', '.', trim((string) $name));

        return $name === '' ? null : $name;
    }

    /**
     * Query PreDB for a show and return the subset of candidate episodes that
     * have a non-nuked release. Each returned episode receives a `predb_quality`
     * attribute carrying the highest detected ReleaseQuality.
     *
     * Performs exactly one HTTP call per show.
     *
     * @param  Collection<int, Episode>  $episodes
     * @return Collection<int, Episode>
     */
    public function findAvailableEpisodes(Show $show, Collection $episodes): Collection
    {
        if ($episodes->isEmpty()) {
            return collect();
        }

        $query = $this->buildShowQuery($show);

        if ($query === null) {
            return collect();
        }

        $results = $this->search($query);

        /** @var array<string, ReleaseQuality> $qualityByCode keyed s{season}e{number} */
        $qualityByCode = [];

        foreach ($results as $release) {
            if (($release['status'] ?? -1) === 1) {
                continue;
            }

            $name = (string) ($release['release'] ?? '');

            if (! preg_match('/\bS(\d{1,3})E(\d{1,3})\b/i', $name, $m)) {
                continue;
            }

            $code = 's'.(int) $m[1].'e'.(int) $m[2];
            $quality = ReleaseQuality::fromReleaseName($name);

            if (! $quality instanceof ReleaseQuality) {
                continue;
            }

            if (! isset($qualityByCode[$code]) || $quality->value > $qualityByCode[$code]->value) {
                $qualityByCode[$code] = $quality;
            }
        }

        return $episodes
            ->filter(function (Episode $episode) use ($qualityByCode): bool {
                $code = 's'.(int) $episode->season.'e'.(int) $episode->number;

                if (! isset($qualityByCode[$code])) {
                    return false;
                }

                $episode->predb_quality = $qualityByCode[$code];

                return true;
            })
            ->values();
    }

    private function baseUrl(): string
    {
        return config('services.predb.base_url', 'https://api.predb.net');
    }

    private function client(): PendingRequest
    {
        return Http::accept('application/json')
            ->timeout(15)
            ->retry(3, 1000, when: fn ($e, $request): bool => $e instanceof RequestException && $e->response->status() === 429);
    }
}
