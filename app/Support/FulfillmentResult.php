<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Episode;
use App\Models\Movie;
use Illuminate\Support\Collection;

final readonly class FulfillmentResult
{
    /**
     * @param  list<array{torrent_id: int, filename: string}>  $downloads
     * @param  Collection<int, Movie|Episode>  $covered
     */
    public function __construct(
        public array $downloads,
        public Collection $covered,
    ) {}
}
