<?php

declare(strict_types=1);

namespace App\Enums;

enum TMDBReleaseType: int
{
    case Premiere = 1;
    case TheatricalLimited = 2;
    case Theatrical = 3;
    case Digital = 4;
    case Physical = 5;
    case Tv = 6;

    public function label(): string
    {
        return match ($this) {
            self::Premiere => 'Premiere',
            self::TheatricalLimited => 'Limited Theatrical',
            self::Theatrical => 'Theatrical',
            self::Digital => 'Digital',
            self::Physical => 'Physical',
            self::Tv => 'TV',
        };
    }
}
