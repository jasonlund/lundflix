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

final class SeasonPackByImdbFinder implements DetailedTorrentFinder
{
    public function __construct(private readonly IptorrentsService $iptorrents) {}

    public function supports(TorrentRequest $request): bool
    {
        if ($request->kind !== Kind::SeasonPack) {
            return false;
        }

        $show = $request->show();

        return $show->imdb_id !== null && $show->imdb_id !== '';
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
        $token = sprintf('S%02d', $season);
        $results = $this->iptorrents->search("{$show->imdb_id} {$token}", $categories);

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
