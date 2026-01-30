<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum TvArtworkLevel: string
{
    case Show = 'show';
    case Season = 'season';

    public function getLabel(): string
    {
        return Str::headline($this->name);
    }
}
