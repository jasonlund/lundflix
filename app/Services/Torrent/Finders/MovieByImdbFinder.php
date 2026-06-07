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

final readonly class MovieByImdbFinder implements TorrentFinder
{
    public function __construct(private IptorrentsService $iptorrents) {}

    public function supports(TorrentRequest $request): bool
    {
        return $request->kind === Kind::Movie && $request->movie()->imdb_id !== '';
    }

    public function find(TorrentRequest $request): ?array
    {
        return $this->findDetailed($request)->match;
    }

    public function findDetailed(TorrentRequest $request): FinderResult
    {
        $movie = $request->movie();
        $categories = array_map(IptCategory::from(...), IptCategory::defaultMovieValues());
        $results = $this->iptorrents->search($movie->imdb_id, $categories);

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
