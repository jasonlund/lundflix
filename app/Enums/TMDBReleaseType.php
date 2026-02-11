<?php

namespace App\Enums;

enum TMDBReleaseType: int
{
    case Premiere = 1;
    case TheatricalLimited = 2;
    case Theatrical = 3;
    case Digital = 4;
    case Physical = 5;
    case Tv = 6;
}
