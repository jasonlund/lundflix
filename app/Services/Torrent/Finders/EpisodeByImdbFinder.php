<?php

declare(strict_types=1);

namespace App\Services\Torrent\Finders;

use App\Enums\IptCategory;
use App\Services\IptorrentsService;
use App\Services\Torrent\FinderResult;
use App\Services\Torrent\Kind;
use App\Services\Torrent\Support\VerifiedResultPicker;
use App\Services\Torrent\TorrentFinder;
use App\Services\Torrent\TorrentRequest;

final class EpisodeByImdbFinder implements TorrentFinder
{
    public function __construct(private readonly IptorrentsService $iptorrents) {}

    public function supports(TorrentRequest $request): bool
    {
        if ($request->kind !== Kind::Episode) {
            return false;
        }

        $episode = $request->episode();
        $episode->loadMissing('show');

        return $episode->show !== null && $episode->show->imdb_id !== null && $episode->show->imdb_id !== '';
    }

    public function find(TorrentRequest $request): ?array
    {
        return $this->findDetailed($request)->match;
    }

    public function findDetailed(TorrentRequest $request): FinderResult
    {
        $episode = $request->episode();
        $episode->loadMissing('show');
        $categories = array_map(IptCategory::from(...), IptCategory::defaultTvValues());
        $results = $this->iptorrents->search("{$episode->show->imdb_id} {$episode->code}", $categories);

        $oversizeSeen = false;

        foreach ($results as $result) {
            if (VerifiedResultPicker::fits($result['size'], $request->maxBytes)) {
                return FinderResult::hit($result);
            }

            $oversizeSeen = true;
        }

        return FinderResult::none($oversizeSeen);
    }
}
