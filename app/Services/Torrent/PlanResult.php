<?php

declare(strict_types=1);

namespace App\Services\Torrent;

use App\Models\RequestItem;

final class PlanResult
{
    /**
     * @param  list<array{torrent_id: int, filename: string}>  $downloads
     * @param  list<RequestItem>  $notFound
     * @param  list<RequestItem>  $oversize
     * @param  list<array{show: \App\Models\Show, season: int, pack: array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}, requestItems: list<RequestItem>}>  $multiSeasonReview
     * @param  list<RequestItem>  $packCovered
     */
    public function __construct(
        public readonly array $downloads,
        public readonly array $notFound,
        public readonly array $oversize,
        public readonly array $multiSeasonReview,
        public readonly array $packCovered,
    ) {}

    public static function empty(): self
    {
        return new self([], [], [], [], []);
    }
}
