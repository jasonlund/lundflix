<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\IptCategory;
use App\Exceptions\IptorrentsAuthException;
use App\Exceptions\IptorrentsRateLimitExceededException;
use App\Models\Episode;
use App\Models\Movie;
use App\Settings\IptorrentsSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\DomCrawler\Crawler;

class IptorrentsService
{
    private const RATE_LIMIT_KEY = 'iptorrents';

    private const RATE_LIMIT_ATTEMPTS = 10;

    private const RATE_LIMIT_DECAY = 60;

    /**
     * Search IPTorrents and return parsed results (max 50 per search).
     *
     * @param  list<IptCategory>  $categories
     * @return Collection<int, array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}>
     */
    public function search(string $query, array $categories = [], string $sort = 'seeders'): Collection
    {
        $this->checkRateLimit();

        $url = $this->buildSearchUrl($query, $categories, $sort);
        $response = $this->client()->get($url);
        $response->throw();

        $html = $response->body();
        $this->detectAuthFailure($html);

        return $this->parseSearchResults($html);
    }

    /**
     * @return array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}|null
     */
    public function searchMovie(Movie $movie): ?array
    {
        $defaultCategories = array_map(
            IptCategory::from(...),
            IptCategory::defaultMovieValues(),
        );

        $queries = [];

        if ($movie->imdb_id) {
            $queries[] = $movie->imdb_id;
        }

        $titleQuery = $movie->title.($movie->year ? ' '.$movie->year : '');
        $queries[] = $titleQuery;

        foreach ($queries as $query) {
            $results = $this->search($query, $defaultCategories);

            if ($results->isNotEmpty()) {
                return $results->first();
            }
        }

        return null;
    }

    /**
     * @return array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}|null
     */
    public function searchEpisode(Episode $episode): ?array
    {
        $episode->loadMissing('show');

        $defaultCategories = array_map(
            IptCategory::from(...),
            IptCategory::defaultTvValues(),
        );
        $allCategories = IptCategory::tvCases();

        $queries = [];

        if ($episode->show->imdb_id) {
            $queries[] = ["{$episode->show->imdb_id} {$episode->code}", $defaultCategories];
            $queries[] = ["{$episode->show->imdb_id} {$episode->code}", $allCategories];
        }

        $queries[] = ["{$episode->show->name} {$episode->code}", $defaultCategories];
        $queries[] = ["{$episode->show->name} {$episode->code}", $allCategories];

        foreach ($queries as [$query, $categories]) {
            $results = $this->search($query, $categories);

            if ($results->isNotEmpty()) {
                return $results->first();
            }
        }

        return null;
    }

    /**
     * Download a .torrent file and store it locally.
     *
     * @return string Absolute path to the stored file
     */
    public function download(int $torrentId, string $filename): string
    {
        $this->checkRateLimit();

        $url = $this->baseUrl()."/download.php/{$torrentId}/{$filename}";
        $response = $this->client()->get($url);
        $response->throw();

        if (str_contains($response->body(), '<title>IPTorrents')) {
            throw new IptorrentsAuthException;
        }

        $path = "private/torrents/{$filename}";
        Storage::disk('local')->put($path, $response->body());

        return Storage::disk('local')->path($path);
    }

    private function buildSearchUrl(string $query, array $categories, string $sort): string
    {
        $params = [];

        if ($categories !== []) {
            $params[] = IptCategory::queryString($categories);
        }

        $params[] = 'q='.urlencode($query);
        $params[] = 'qf=';
        $params[] = 'o='.urlencode($sort);
        $params[] = 'qq=desc';

        return $this->baseUrl().'/t?'.implode('&', $params).'#torrents';
    }

    /**
     * @return Collection<int, array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}>
     */
    private function parseSearchResults(string $html): Collection
    {
        $crawler = new Crawler($html);
        $results = collect();

        try {
            $rows = $crawler->filter('table#torrents tbody tr');
        } catch (\InvalidArgumentException) {
            return $results;
        }

        if ($rows->count() === 0) {
            return $results;
        }

        $rows->each(function (Crawler $row) use ($results): void {
            try {
                $downloadLink = $row->filter('a[href*="/download.php/"]');

                if ($downloadLink->count() === 0) {
                    return;
                }

                $href = $downloadLink->first()->attr('href') ?? '';

                if (! preg_match('#/download\.php/(\d+)/#', $href, $matches)) {
                    return;
                }

                $torrentId = (int) $matches[1];

                $nameLink = $row->filter('td:nth-child(2) a.hv');
                $name = $nameLink->count() > 0 ? trim($nameLink->first()->text()) : '';

                // Upload time lives in a .sub div inside the name cell
                $subDiv = $row->filter('td:nth-child(2) .sub');
                $uploaded = $subDiv->count() > 0 ? trim($subDiv->first()->text()) : '';

                // Columns: 0=cat, 1=name, 2=bookmark, 3=download, 4=comments, 5=size, 6=seeders, 7=leechers, 8=snatches
                $cells = $row->filter('td');

                $results->push([
                    'torrent_id' => $torrentId,
                    'name' => $name,
                    'size' => $cells->count() > 5 ? trim($cells->eq(5)->text()) : '',
                    'seeders' => $cells->count() > 6 ? (int) trim($cells->eq(6)->text()) : 0,
                    'leechers' => $cells->count() > 7 ? (int) trim($cells->eq(7)->text()) : 0,
                    'snatches' => $cells->count() > 8 ? (int) trim($cells->eq(8)->text()) : 0,
                    'uploaded' => $uploaded,
                    'download_url' => $this->baseUrl().$href,
                ]);
            } catch (\Throwable) {
                // Skip malformed rows
            }
        });

        return $results;
    }

    private function detectAuthFailure(string $html): void
    {
        if (str_contains($html, '<title>IPTorrents :: Login</title>')) {
            throw new IptorrentsAuthException;
        }

        $crawler = new Crawler($html);

        try {
            if ($crawler->filter('form[action*="take_login"]')->count() > 0) {
                throw new IptorrentsAuthException;
            }
        } catch (IptorrentsAuthException $e) {
            throw $e;
        } catch (\Throwable) {
            // Parsing error — not an auth failure
        }
    }

    private function checkRateLimit(): void
    {
        if (RateLimiter::tooManyAttempts(self::RATE_LIMIT_KEY, self::RATE_LIMIT_ATTEMPTS)) {
            throw new IptorrentsRateLimitExceededException;
        }

        RateLimiter::hit(self::RATE_LIMIT_KEY, self::RATE_LIMIT_DECAY);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.iptorrents.base_url', 'https://iptorrents.com'), '/');
    }

    private function client(): PendingRequest
    {
        $settings = app(IptorrentsSettings::class);

        if (! $settings->isConfigured()) {
            throw new IptorrentsAuthException('IPTorrents credentials not configured. Set them in admin Settings → IPTorrents.');
        }

        return Http::resilient()
            ->withHeaders(['Cookie' => $settings->cookieHeader()])
            ->timeout(30);
    }
}
