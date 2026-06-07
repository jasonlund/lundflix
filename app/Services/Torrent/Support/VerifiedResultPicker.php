<?php

declare(strict_types=1);

namespace App\Services\Torrent\Support;

use App\Services\IptorrentsService;
use App\Services\Torrent\FinderResult;
use App\Support\TorrentSize;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class VerifiedResultPicker
{
    public const MAX_IMDB_LOOKUPS = 5;

    public function __construct(private readonly IptorrentsService $iptorrents) {}

    /**
     * Iterate results in order, dedupe by show-title prefix, size-check, IMDB-verify.
     * Returns the first result whose detail page IMDB matches and size fits.
     *
     * @param  Collection<int, array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}>  $results
     * @return array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}|null
     */
    public function pick(Collection $results, string $expectedImdbId, int $maxBytes, int $maxLookups = self::MAX_IMDB_LOOKUPS): ?array
    {
        return $this->pickDetailed($results, $expectedImdbId, $maxBytes, $maxLookups)->match;
    }

    /**
     * Like pick(), but also reports whether any IMDB-verified candidate existed that was over the size cap.
     *
     * Performs a single pass: tries fitting matches first; if none found, runs an oversize verification
     * pass using the remaining lookup budget so we can distinguish "no match" from "too big".
     *
     * @param  Collection<int, array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}>  $results
     */
    public function pickDetailed(Collection $results, string $expectedImdbId, int $maxBytes, int $maxLookups = self::MAX_IMDB_LOOKUPS): FinderResult
    {
        $seenPrefixes = [];
        $seenPrefixesOversize = [];
        $lookups = 0;
        $skippedOversize = [];

        foreach ($results as $result) {
            $prefix = self::prefixKey($result['name']);

            if ($prefix !== null && isset($seenPrefixes[$prefix])) {
                continue;
            }

            if (! $this->fits($result['size'], $maxBytes)) {
                $skippedOversize[] = $result;

                continue;
            }

            if ($lookups >= $maxLookups) {
                break;
            }

            $lookups++;

            if ($this->iptorrents->fetchTorrentImdbId($result['torrent_id']) === $expectedImdbId) {
                return FinderResult::hit($result);
            }

            if ($prefix !== null) {
                $seenPrefixes[$prefix] = true;
            }
        }

        foreach ($skippedOversize as $result) {
            $prefix = self::prefixKey($result['name']);

            if ($prefix !== null && isset($seenPrefixesOversize[$prefix])) {
                continue;
            }

            if ($lookups >= $maxLookups) {
                break;
            }

            $lookups++;

            if ($this->iptorrents->fetchTorrentImdbId($result['torrent_id']) === $expectedImdbId) {
                return FinderResult::none(oversize: true);
            }

            if ($prefix !== null) {
                $seenPrefixesOversize[$prefix] = true;
            }
        }

        return FinderResult::none();
    }

    public static function fits(string $size, int $maxBytes): bool
    {
        try {
            return TorrentSize::parse($size) <= $maxBytes;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public static function prefixKey(string $torrentName): ?string
    {
        if (preg_match('/^(.+?)\s+(?:[Ss]\d{1,2}[Ee]\d{1,2}|[Ss]\d{1,2}(?:[\s.\-_]|$)|\d{4}[.\-]\d{2}[.\-]\d{2})/', $torrentName, $matches)) {
            return mb_strtolower(trim($matches[1]));
        }

        return null;
    }
}
