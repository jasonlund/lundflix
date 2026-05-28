<?php

declare(strict_types=1);

namespace App\Services\Torrent;

enum Kind: string
{
    case Movie = 'movie';
    case Episode = 'episode';
    case SeasonPack = 'season_pack';
}
