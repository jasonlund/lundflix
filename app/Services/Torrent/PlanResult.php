<?php

declare(strict_types=1);

namespace App\Services\Torrent;

use App\Models\RequestItem;
use App\Models\Show;

final readonly class PlanResult
{
    /**
     * @param  list<array{torrent_id: int, filename: string}>  $downloads
     * @param  list<RequestItem>  $notFound
     * @param  list<array{item: RequestItem, maxBytes: int}>  $oversize
     * @param  list<array{show: Show, season: int, pack: array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}, requestItems: list<RequestItem>}>  $multiSeasonReview
     * @param  list<RequestItem>  $packCovered
     */
    public function __construct(
        public array $downloads,
        public array $notFound,
        public array $oversize,
        public array $multiSeasonReview,
        public array $packCovered,
    ) {}

    public static function empty(): self
    {
        return new self([], [], [], [], []);
    }
}
