<?php

declare(strict_types=1);

namespace App\Services\Torrent\Finders;

use App\Enums\IptCategory;
use App\Services\IptorrentsService;
use App\Services\Torrent\DetailedTorrentFinder;
use App\Services\Torrent\FinderResult;
use App\Services\Torrent\Kind;
use App\Services\Torrent\Support\VerifiedResultPicker;
use App\Services\Torrent\TorrentRequest;

final class SeasonPackByNameFinder implements DetailedTorrentFinder
{
    public function __construct(
        private readonly IptorrentsService $iptorrents,
        private readonly VerifiedResultPicker $picker,
    ) {}

    public function supports(TorrentRequest $request): bool
    {
        if ($request->kind !== Kind::SeasonPack) {
            return false;
        }

        $show = $request->show();

        return $show->imdb_id !== null
            && $show->imdb_id !== ''
            && trim((string) $show->name) !== '';
    }

    public function find(TorrentRequest $request): ?array
    {
        return $this->findDetailed($request)->match;
    }

    public function findDetailed(TorrentRequest $request): FinderResult
    {
        $show = $request->show();
        $season = $request->season();
        $categories = [IptCategory::TvPacks, IptCategory::TvPacksNonEnglish];

        $terms = $this->resolveTerms($show->ipt_search_terms, $show->name);

        if ($terms === []) {
            return FinderResult::none();
        }

        $token = sprintf('S%02d', $season);
        $query = $this->buildOrQuery($terms)." {$token}";
        $results = $this->iptorrents->search($query, $categories);

        return $this->picker->pickDetailed($results, $show->imdb_id, $request->maxBytes);
    }

    /**
     * @param  mixed  $stored
     * @return list<string>
     */
    private function resolveTerms($stored, ?string $fallbackName): array
    {
        $terms = is_array($stored) ? array_values(array_filter(
            array_map(static fn ($t): string => is_string($t) ? trim($t) : '', $stored),
            static fn (string $t): bool => $t !== '',
        )) : [];

        if ($terms !== []) {
            return $terms;
        }

        $sanitized = self::sanitize((string) $fallbackName);

        return $sanitized === '' ? [] : [$sanitized];
    }

    /**
     * @param  list<string>  $terms
     */
    private function buildOrQuery(array $terms): string
    {
        if (count($terms) === 1) {
            return $terms[0];
        }

        return implode('|', array_map(
            static fn (string $t): string => '"'.str_replace('"', '', $t).'"',
            $terms,
        ));
    }

    private static function sanitize(string $name): string
    {
        $name = (string) preg_replace('/[\x{2010}-\x{2015}\x{2D}]+/u', ' ', $name);

        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^\p{L}\p{N}\s]/u', '', $name)));
    }
}
