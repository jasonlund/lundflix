<?php

declare(strict_types=1);

namespace App\Services\Torrent\Finders;

use App\Enums\IptCategory;
use App\Services\IptorrentsService;
use App\Services\Torrent\FinderResult;
use App\Services\Torrent\Kind;
use App\Services\Torrent\Support\SearchTermBuilder;
use App\Services\Torrent\Support\VerifiedResultPicker;
use App\Services\Torrent\TorrentFinder;
use App\Services\Torrent\TorrentRequest;

final readonly class SeasonPackByNameFinder implements TorrentFinder
{
    public function __construct(
        private IptorrentsService $iptorrents,
        private VerifiedResultPicker $picker,
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

        $terms = SearchTermBuilder::resolveTerms($show->ipt_search_terms, $show->getRawOriginal('name'));

        if ($terms === []) {
            return FinderResult::none();
        }

        $token = sprintf('S%02d', $season);
        $query = SearchTermBuilder::buildOrQuery($terms)." {$token}";
        $results = $this->iptorrents->search($query, $categories);

        return $this->picker->pickDetailed($results, $show->imdb_id, $request->maxBytes);
    }
}
