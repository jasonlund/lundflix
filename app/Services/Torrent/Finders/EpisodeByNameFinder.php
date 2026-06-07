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

final class EpisodeByNameFinder implements TorrentFinder
{
    public function __construct(
        private readonly IptorrentsService $iptorrents,
        private readonly VerifiedResultPicker $picker,
    ) {}

    public function supports(TorrentRequest $request): bool
    {
        if ($request->kind !== Kind::Episode) {
            return false;
        }

        $episode = $request->episode();
        $episode->loadMissing('show');

        return $episode->show !== null
            && $episode->show->imdb_id !== null
            && $episode->show->imdb_id !== ''
            && trim((string) $episode->show->name) !== '';
    }

    public function find(TorrentRequest $request): ?array
    {
        return $this->findDetailed($request)->match;
    }

    public function findDetailed(TorrentRequest $request): FinderResult
    {
        $episode = $request->episode();
        $episode->loadMissing('show');
        $show = $episode->show;
        $categories = array_map(IptCategory::from(...), IptCategory::defaultTvValues());

        $terms = SearchTermBuilder::resolveTerms($show->ipt_search_terms, $show->getRawOriginal('name'));

        if ($terms === []) {
            return FinderResult::none();
        }

        $query = SearchTermBuilder::buildOrQuery($terms)." {$episode->code}";
        $results = $this->iptorrents->search($query, $categories);

        return $this->picker->pickDetailed($results, $show->imdb_id, $request->maxBytes);
    }
}
