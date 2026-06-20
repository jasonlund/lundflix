<?php

declare(strict_types=1);

namespace App\Enums;

enum TorrentSearchStrategy: string
{
    case Name = 'name';
    case ImdbId = 'imdb_id';
}
