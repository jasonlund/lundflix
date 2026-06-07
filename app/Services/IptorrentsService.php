<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\IptCategory;
use App\Exceptions\IptorrentsAuthException;
use App\Exceptions\IptorrentsRateLimitExceededException;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use App\Services\Torrent\Support\PackNameParser;
use App\Services\Torrent\Support\SearchTermBuilder;
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

    private const RATE_LIMIT_ATTEMPTS = 20;

    private const RATE_LIMIT_DECAY = 60;

    private const MAX_IMDB_LOOKUPS = 5;

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
        if (! $movie->imdb_id) {
            return null;
        }

        $defaultCategories = array_map(
            IptCategory::from(...),
            IptCategory::defaultMovieValues(),
        );

        $results = $this->search($movie->imdb_id, $defaultCategories);

        return $results->first();
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

        $terms = SearchTermBuilder::resolveTerms($movie->ipt_search_terms, $movie->title);

        if ($terms === []) {
            return null;
        }

        $query = SearchTermBuilder::buildOrQuery($terms).($movie->year ? ' '.$movie->year : '');
        $results = $this->search($query, $categories);

        $seenPrefixes = [];
        $lookups = 0;

        foreach ($results as $result) {
            $prefix = $this->prefixKey($result['name']);

            if ($prefix !== null && isset($seenPrefixes[$prefix])) {
                continue;
            }

            if ($lookups >= self::MAX_IMDB_LOOKUPS) {
                break;
            }

            $lookups++;

            if ($this->fetchTorrentImdbId($result['torrent_id']) === $movie->imdb_id) {
                return $result;
            }

            if ($prefix !== null) {
                $seenPrefixes[$prefix] = true;
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

        if (! $episode->show->imdb_id) {
            return null;
        }

        $categories = array_map(
            IptCategory::from(...),
            IptCategory::defaultTvValues(),
        );

        $results = $this->search("{$episode->show->imdb_id} {$episode->code}", $categories);

        return $results->first();
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

        $existingTerms = $episode->show->ipt_search_terms ?? [];
        $learnEnabled = $existingTerms === [];
        $terms = SearchTermBuilder::resolveTerms($existingTerms, $episode->show->getRawOriginal('name'));

        if ($terms === []) {
            return null;
        }

        $query = SearchTermBuilder::buildOrQuery($terms)." {$episode->code}";
        $results = $this->search($query, $categories);

        $seenPrefixes = [];
        $lookups = 0;

        foreach ($results as $index => $result) {
            $prefix = $this->prefixKey($result['name']);

            if ($prefix !== null && isset($seenPrefixes[$prefix])) {
                continue;
            }

            if ($lookups >= self::MAX_IMDB_LOOKUPS) {
                break;
            }

            $lookups++;

            if ($this->fetchTorrentImdbId($result['torrent_id']) === $episode->show->imdb_id) {
                if ($learnEnabled) {
                    $this->learnSearchTerm($episode, $result['name'], $index);
                }

                return $result;
            }

            if ($prefix !== null) {
                $seenPrefixes[$prefix] = true;
            }
        }

        return null;
    }

    /**
     * @return array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}|null
     */
    public function searchSeasonPack(Show $show, int $season): ?array
    {
        if (! $show->imdb_id) {
            return null;
        }

        $categories = [IptCategory::TvPacks, IptCategory::TvPacksNonEnglish];
        $token = sprintf('S%02d', $season);

        return $this->search("{$show->imdb_id} {$token}", $categories)->first();
    }

    /**
     * @return array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}|null
     */
    public function searchSeasonPackByName(Show $show, int $season): ?array
    {
        if (! $show->imdb_id) {
            return null;
        }

        $categories = [IptCategory::TvPacks, IptCategory::TvPacksNonEnglish];
        $terms = SearchTermBuilder::resolveTerms($show->ipt_search_terms, $show->name);

        if ($terms === []) {
            return null;
        }

        $token = sprintf('S%02d', $season);
        $query = SearchTermBuilder::buildOrQuery($terms)." {$token}";
        $results = $this->search($query, $categories);

        $seenPrefixes = [];
        $lookups = 0;

        foreach ($results as $result) {
            $prefix = $this->prefixKey($result['name']);

            if ($prefix !== null && isset($seenPrefixes[$prefix])) {
                continue;
            }

            if ($lookups >= self::MAX_IMDB_LOOKUPS) {
                break;
            }

            $lookups++;

            if ($this->fetchTorrentImdbId($result['torrent_id']) === $show->imdb_id) {
                return $result;
            }

            if ($prefix !== null) {
                $seenPrefixes[$prefix] = true;
            }
        }

        return null;
    }

    /**
     * Detect a multi-season pack whose name claims to cover the requested season.
     *
     * Detection only — does NOT apply the size cap. Caller decides what to do
     * with the match (typically: notify, do not download).
     *
     * @return array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}|null
     */
    public function searchMultiSeasonPack(Show $show, int $season): ?array
    {
        if (! $show->imdb_id) {
            return null;
        }

        $categories = [IptCategory::TvPacks, IptCategory::TvPacksNonEnglish];
        $results = $this->search($show->imdb_id, $categories);

        foreach ($results as $result) {
            if ($this->multiSeasonPackCovers($result['name'], $season)) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Name-based fallback for multi-season pack detection.
     *
     * @return array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}|null
     */
    public function searchMultiSeasonPackByName(Show $show, int $season): ?array
    {
        if (! $show->imdb_id) {
            return null;
        }

        $categories = [IptCategory::TvPacks, IptCategory::TvPacksNonEnglish];
        $terms = SearchTermBuilder::resolveTerms($show->ipt_search_terms, $show->name);

        if ($terms === []) {
            return null;
        }

        $results = $this->search(SearchTermBuilder::buildOrQuery($terms), $categories);

        $lookups = 0;

        foreach ($results as $result) {
            if (! $this->multiSeasonPackCovers($result['name'], $season)) {
                continue;
            }

            if ($lookups >= self::MAX_IMDB_LOOKUPS) {
                break;
            }

            $lookups++;

            if ($this->fetchTorrentImdbId($result['torrent_id']) === $show->imdb_id) {
                return $result;
            }
        }

        return null;
    }

    private function multiSeasonPackCovers(string $name, int $season): bool
    {
        $parsed = PackNameParser::parse($name);

        if ($parsed === null) {
            return false;
        }

        return match ($parsed['type']) {
            'range' => $season >= $parsed['start'] && $season <= $parsed['end'],
            'complete' => true,
            default => false,
        };
    }

    /**
     * Fetch the IMDB ID from a torrent's detail page.
     */
    public function fetchTorrentImdbId(int $torrentId): ?string
    {
        $this->checkRateLimit();

        $url = $this->baseUrl()."/torrent.php?id={$torrentId}";
        $response = $this->client()->get($url);
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

    /**
     * Persist a learned IPTorrents search term for a show when its release title
     * diverges from the canonical show name.
     *
     * Gating contract: callers MUST only invoke this when the show has no learned
     * terms yet (`ipt_search_terms` empty). This method does NOT re-check that
     * precondition before doing rate-limited work (search + IMDb lookup). The
     * `update()` WHERE clause is a secondary net that prevents overwriting an
     * existing learned term, but it does not save the wasted lookups.
     *
     * @param  Episode  $episode  Episode whose show may receive a learned term.
     * @param  string  $torrentName  Release name a match was found under.
     * @param  int  $matchIndex  Index of the match; 0 means the canonical name already matched.
     */
    private function learnSearchTerm(Episode $episode, string $torrentName, int $matchIndex): void
    {
        if ($matchIndex === 0) {
            return;
        }

        $showTitle = $this->extractShowTitle($torrentName);

        if ($showTitle === null) {
            return;
        }

        if (mb_strtolower($showTitle) === mb_strtolower(SearchTermBuilder::sanitize($episode->show->getRawOriginal('name')))) {
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
            ->where(fn ($q) => $q->whereNull('ipt_search_terms')->orWhere('ipt_search_terms', '[]'))
            ->update(['ipt_search_terms' => json_encode([$showTitle])]);
    }

    private function prefixKey(string $torrentName): ?string
    {
        $title = $this->extractShowTitle($torrentName);

        return $title === null ? null : mb_strtolower($title);
    }

    private function extractShowTitle(string $torrentName): ?string
    {
        if (preg_match('/^(.+?)\s+(?:[Ss]\d{1,2}[Ee]\d{1,2}|\d{4}[.\-]\d{2}[.\-]\d{2})/', $torrentName, $matches)) {
            return trim($matches[1]);
        }

        return null;
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
