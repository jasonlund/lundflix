<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\IptCategory;
use App\Exceptions\IptorrentsAuthException;
use App\Exceptions\IptorrentsRateLimitExceededException;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use App\Settings\IptorrentsSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Symfony\Component\DomCrawler\Crawler;

class IptorrentsService
{
    private const THROTTLE_KEY = 'iptorrents:next-slot';

    private const THROTTLE_LOCK = 'iptorrents:throttle-lock';

    /**
     * Minimum spacing between IPTorrents requests. Spreads ~10 requests across
     * 65 seconds (one every 6.5s), staying safely under the tracker's measured
     * burst ceiling of ~10 requests before it returns HTTP 429.
     */
    private const REQUEST_SPACING_MS = 6500;

    /**
     * Longest a worker will wait in-process for its slot before releasing the
     * job back to the queue instead of blocking.
     */
    private const MAX_WAIT_SECONDS = 30;

    /** Fallback cooldown when a 429 arrives without a usable Retry-After header. */
    private const RETRY_AFTER_FALLBACK_SECONDS = 60;

    private const MAX_IMDB_LOOKUPS = 3;

    private const MAX_RAR_CHECKS = 3;

    /**
     * Search IPTorrents and return parsed results (max 50 per search).
     *
     * @param  list<IptCategory>  $categories
     * @return Collection<int, array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}>
     */
    public function search(string $query, array $categories = [], string $sort = 'seeders'): Collection
    {
        $this->throttleRequest();

        $url = $this->buildSearchUrl($query, $categories, $sort);
        $response = $this->client()->get($url);
        $this->guardRateLimitResponse($response);
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
        if (! $movie->imdb_id) {
            return null;
        }

        $defaultCategories = array_map(
            IptCategory::from(...),
            IptCategory::defaultMovieValues(),
        );

        $results = $this->preferH265($this->search($movie->imdb_id, $defaultCategories));

        return $this->firstNonRar($results);
    }

    /**
     * @return array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}|null
     */
    public function searchMovieByName(Movie $movie): ?array
    {
        if (! $movie->imdb_id) {
            return null;
        }

        $categories = array_map(
            IptCategory::from(...),
            IptCategory::defaultMovieValues(),
        );

        $searchName = $this->sanitizeNameForSearch($movie->title);

        if ($searchName === '') {
            return null;
        }

        $query = $searchName.($movie->year ? ' '.$movie->year : '');
        $results = $this->preferH265($this->search($query, $categories));

        $rarFallback = null;

        foreach ($results->take(self::MAX_IMDB_LOOKUPS) as $result) {
            if ($this->fetchTorrentImdbId($result['torrent_id']) !== $movie->imdb_id) {
                continue;
            }

            if ($this->isRarFree($result)) {
                return $result;
            }

            $rarFallback ??= $result;
        }

        return $rarFallback;
    }

    /**
     * @return array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}|null
     */
    public function searchEpisode(Episode $episode): ?array
    {
        $episode->loadMissing('show');

        if (! $episode->show->imdb_id) {
            return null;
        }

        $categories = array_map(
            IptCategory::from(...),
            IptCategory::defaultTvValues(),
        );

        $results = $this->preferH265($this->search("{$episode->show->imdb_id} {$episode->code}", $categories));

        return $this->firstNonRar($results);
    }

    /**
     * @return array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}|null
     */
    public function searchEpisodeByName(Episode $episode): ?array
    {
        $episode->loadMissing('show');

        if (! $episode->show->imdb_id) {
            return null;
        }

        $categories = array_map(
            IptCategory::from(...),
            IptCategory::defaultTvValues(),
        );

        $searchName = $episode->show->ipt_search_term
            ?? $this->sanitizeNameForSearch($episode->show->getRawOriginal('name'));

        if ($searchName === '') {
            return null;
        }

        $query = "{$searchName} {$episode->code}";
        $results = $this->preferH265($this->search($query, $categories));

        $rarFallback = null;
        $rarFallbackIndex = null;

        foreach ($results->take(self::MAX_IMDB_LOOKUPS) as $index => $result) {
            if ($this->fetchTorrentImdbId($result['torrent_id']) !== $episode->show->imdb_id) {
                continue;
            }

            if ($this->isRarFree($result)) {
                $this->learnSearchTerm($episode, $result['name'], $index);

                return $result;
            }

            if ($rarFallback === null) {
                $rarFallback = $result;
                $rarFallbackIndex = $index;
            }
        }

        if ($rarFallback !== null) {
            $this->learnSearchTerm($episode, $rarFallback['name'], $rarFallbackIndex);
        }

        return $rarFallback;
    }

    /**
     * Fetch the IMDB ID from a torrent's detail page.
     */
    public function fetchTorrentImdbId(int $torrentId): ?string
    {
        $this->throttleRequest();

        $url = $this->baseUrl()."/torrent.php?id={$torrentId}";
        $response = $this->client()->get($url);
        $this->guardRateLimitResponse($response);
        $response->throw();

        $html = $response->body();
        $this->detectAuthFailure($html);

        $crawler = new Crawler($html);

        try {
            $imdbLink = $crawler->filter('a[href*="imdb.com/title/"]');

            if ($imdbLink->count() === 0) {
                return null;
            }

            $href = $imdbLink->first()->attr('href') ?? '';

            if (preg_match('/(tt\d+)/', $href, $matches)) {
                return $matches[1];
            }
        } catch (\Throwable) {
            // Parsing error
        }

        return null;
    }

    /**
     * Fetch the relative file paths listed on a torrent's file page.
     *
     * @return list<string>
     */
    public function fetchTorrentFileList(int $torrentId): array
    {
        $this->throttleRequest();

        $url = $this->baseUrl()."/t/{$torrentId}/files";
        $response = $this->client()->get($url);
        $this->guardRateLimitResponse($response);
        $response->throw();

        $html = $response->body();
        $this->detectAuthFailure($html);

        $crawler = new Crawler($html);
        $files = [];

        try {
            $crawler->filter('table.t1 tr')->each(function (Crawler $row) use (&$files): void {
                $cells = $row->filter('td');

                if ($cells->count() > 0) {
                    $files[] = trim($cells->first()->text());
                }
            });
        } catch (\Throwable) {
            return [];
        }

        return $files;
    }

    /**
     * Download a .torrent file and store it locally.
     *
     * @return string Absolute path to the stored file
     */
    public function download(int $torrentId, string $filename): string
    {
        $this->throttleRequest();

        $url = $this->baseUrl()."/download.php/{$torrentId}/{$filename}";
        $response = $this->client()->get($url);
        $this->guardRateLimitResponse($response);
        $response->throw();

        if (str_contains($response->body(), '<title>IPTorrents')) {
            throw new IptorrentsAuthException;
        }

        $path = "private/torrents/{$filename}";
        Storage::disk('local')->put($path, $response->body());

        return Storage::disk('local')->path($path);
    }

    private function learnSearchTerm(Episode $episode, string $torrentName, int $matchIndex): void
    {
        if ($episode->show->ipt_search_term !== null || $matchIndex === 0) {
            return;
        }

        $showTitle = $this->extractShowTitle($torrentName);

        if ($showTitle === null) {
            return;
        }

        if (mb_strtolower($showTitle) === mb_strtolower($this->sanitizeNameForSearch($episode->show->getRawOriginal('name')))) {
            return;
        }

        try {
            $categories = array_map(
                IptCategory::from(...),
                IptCategory::defaultTvValues(),
            );

            $verificationResults = $this->search(
                "{$showTitle} {$episode->code}",
                $categories,
            );

            if ($verificationResults->isEmpty()) {
                return;
            }

            if ($this->fetchTorrentImdbId($verificationResults->first()['torrent_id']) !== $episode->show->imdb_id) {
                return;
            }
        } catch (IptorrentsRateLimitExceededException|IptorrentsAuthException $e) {
            throw $e;
        } catch (\Throwable) {
            return;
        }

        Show::query()
            ->where('id', $episode->show->id)
            ->whereNull('ipt_search_term')
            ->update(['ipt_search_term' => $showTitle]);
    }

    private function extractShowTitle(string $torrentName): ?string
    {
        if (preg_match('/^(.+?)\s+(?:[Ss]\d{1,2}[Ee]\d{1,2}|\d{4}[.\-]\d{2}[.\-]\d{2})/', $torrentName, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Reorder results to prefer H.265/HEVC encodes over H.264/WEB-DL,
     * keeping the original seeder order within each group.
     *
     * @param  Collection<int, array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}>  $results
     * @return Collection<int, array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}>
     */
    private function preferH265(Collection $results): Collection
    {
        return $results
            ->sortBy(fn (array $result): int => $this->isH265($result['name']) ? 0 : 1)
            ->values();
    }

    private function isH265(string $name): bool
    {
        return (bool) preg_match('/(?<![a-z0-9])x\.?\s?265|(?<![a-z0-9])h\.?\s?265|hevc/i', $name);
    }

    /**
     * Return the first release that is not packed into RAR archives, falling
     * back to the top result when none can be confirmed within the check cap.
     *
     * @param  Collection<int, array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}>  $results
     * @return array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}|null
     */
    private function firstNonRar(Collection $results): ?array
    {
        $checks = 0;

        foreach ($results as $result) {
            if ($this->hasNoRarTag($result['name'])) {
                return $result;
            }

            if ($checks >= self::MAX_RAR_CHECKS) {
                break;
            }

            $checks++;

            if ($this->isRarFree($result)) {
                return $result;
            }
        }

        return $results->first();
    }

    /**
     * Determine whether a release is free of RAR-packed payload, trusting a
     * NORAR title tag and otherwise inspecting its file list.
     *
     * @param  array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}  $result
     */
    private function isRarFree(array $result): bool
    {
        if ($this->hasNoRarTag($result['name'])) {
            return true;
        }

        try {
            return ! $this->isRarPacked($this->fetchTorrentFileList($result['torrent_id']));
        } catch (IptorrentsRateLimitExceededException|IptorrentsAuthException $e) {
            throw $e;
        } catch (\Throwable) {
            return true;
        }
    }

    private function hasNoRarTag(string $name): bool
    {
        return (bool) preg_match('/no[\s._-]*rar/i', $name);
    }

    /**
     * Detect a RAR-packed payload from a file list. Multipart volumes (.rNN)
     * always count; a lone .rar counts unless it is a subtitle or sample
     * archive sitting beside the real media.
     *
     * @param  list<string>  $files
     */
    private function isRarPacked(array $files): bool
    {
        foreach ($files as $path) {
            $basename = basename($path);

            if (preg_match('/\.r\d+$/i', $basename)) {
                return true;
            }

            if (preg_match('/\.rar$/i', $basename)) {
                $lowerPath = mb_strtolower($path);

                if (preg_match('#(^|/)(sample[^/]*|subs|subtitles)/#', $lowerPath)) {
                    continue;
                }

                return true;
            }
        }

        return false;
    }

    private function sanitizeNameForSearch(string $name): string
    {
        $name = (string) preg_replace('/[\x{2010}-\x{2015}\x{2D}]+/u', ' ', $name);

        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^\p{L}\p{N}\s]/u', '', $name)));
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

    /**
     * Pace outbound IPTorrents requests so no more than one fires per
     * REQUEST_SPACING_MS, coordinated across all queue workers via a shared
     * cache slot. Short waits are slept off in-process; a wait beyond
     * MAX_WAIT_SECONDS (e.g. an active Retry-After cooldown) releases the job
     * back to the queue instead of blocking the worker.
     */
    private function throttleRequest(): void
    {
        $waitMs = Cache::lock(self::THROTTLE_LOCK, 10)->block(
            self::MAX_WAIT_SECONDS + 5,
            function (): int {
                $now = now()->getTimestampMs();
                $last = (int) Cache::get(self::THROTTLE_KEY, 0);
                $slot = max($now, $last + self::REQUEST_SPACING_MS);

                if (($slot - $now) > self::MAX_WAIT_SECONDS * 1000) {
                    throw new IptorrentsRateLimitExceededException;
                }

                Cache::put(self::THROTTLE_KEY, $slot, now()->addMinutes(5));

                return $slot - $now;
            },
        );

        if ($waitMs > 0) {
            Sleep::for($waitMs)->milliseconds();
        }
    }

    /**
     * Honor an IPTorrents HTTP 429 by logging the Retry-After value, pushing the
     * shared throttle slot out so every worker observes the cooldown, and
     * releasing the job. 429 is deliberately excluded from the client retry
     * policy so this runs instead of a tight, Retry-After-ignoring retry loop.
     */
    private function guardRateLimitResponse(Response $response): void
    {
        if ($response->status() !== 429) {
            return;
        }

        $retryAfter = (int) $response->header('Retry-After');

        if ($retryAfter <= 0) {
            $retryAfter = self::RETRY_AFTER_FALLBACK_SECONDS;
        }

        Log::warning('IPTorrents returned HTTP 429; honoring Retry-After.', [
            'retry_after_seconds' => $retryAfter,
        ]);

        Cache::put(
            self::THROTTLE_KEY,
            now()->getTimestampMs() + ($retryAfter * 1000),
            now()->addMinutes(5),
        );

        throw new IptorrentsRateLimitExceededException($retryAfter);
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

        return Http::retry(3, 1000, when: fn (\Throwable $e): bool => $e instanceof ConnectionException
            || ($e instanceof RequestException && in_array($e->response->status(), [408, 502, 503, 504], true)), throw: false)
            ->withHeaders(['Cookie' => $settings->cookieHeader()])
            ->timeout(30);
    }
}
