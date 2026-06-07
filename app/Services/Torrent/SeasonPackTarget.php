<?php

declare(strict_types=1);

namespace App\Services\Torrent;

use App\Models\Show;

final readonly class SeasonPackTarget
{
    public function __construct(
        public Show $show,
        public int $season,
    ) {}
}
