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

final class MovieByNameFinder implements DetailedTorrentFinder
{
    public function __construct(
        private readonly IptorrentsService $iptorrents,
        private readonly VerifiedResultPicker $picker,
    ) {}

    public function supports(TorrentRequest $request): bool
    {
        return $request->kind === Kind::Movie
            && $request->movie()->imdb_id !== ''
            && trim((string) $request->movie()->title) !== '';
    }

    public function find(TorrentRequest $request): ?array
    {
        return $this->findDetailed($request)->match;
    }

    public function findDetailed(TorrentRequest $request): FinderResult
    {
        $movie = $request->movie();
        $categories = array_map(IptCategory::from(...), IptCategory::defaultMovieValues());

        $term = self::sanitize($movie->title);

        if ($term === '') {
            return FinderResult::none();
        }

        $query = $term.($movie->year ? ' '.$movie->year : '');
        $results = $this->iptorrents->search($query, $categories);

        return $this->picker->pickDetailed($results, $movie->imdb_id, $request->maxBytes);
    }

    private static function sanitize(string $name): string
    {
        $name = (string) preg_replace('/[\x{2010}-\x{2015}\x{2D}]+/u', ' ', $name);

        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^\p{L}\p{N}\s]/u', '', $name)));
    }
}
