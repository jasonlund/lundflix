<?php

namespace App\Services;

use App\Enums\ReleaseQuality;
use App\Models\Movie;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class PreDBService
{
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

            if ($quality === null) {
                continue;
            }

            if ($highest === null || $quality->value > $highest->value) {
                $highest = $quality;
            }
        }

        return $highest;
    }

    /**
     * Search PreDB for releases matching a query, excluding low-quality tags.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, int $limit = 100): array
    {
        $response = $this->client()->get($this->baseUrl(), [
            'q' => $query,
            'tag' => implode(',', array_map(fn (string $tag) => '-'.$tag, ReleaseQuality::excludedTags())),
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
        $title = preg_replace('/[^\w\s.-]/', '', $movie->title);
        $title = (string) preg_replace('/\s+/', '.', trim((string) $title));

        if ($movie->year) {
            $title .= '.'.$movie->year;
        }

        return $title;
    }

    private function baseUrl(): string
    {
        return config('services.predb.base_url', 'https://api.predb.net');
    }

    private function client(): PendingRequest
    {
        return Http::accept('application/json')
            ->timeout(15)
            ->retry(3, 1000, when: fn ($e, $request) => $e instanceof RequestException && $e->response->status() === 429);
    }
}
