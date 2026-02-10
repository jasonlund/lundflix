<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;

enum TvArtworkLevel: string implements HasLabel
{
    case Show = 'show';
    case Season = 'season';

    public function getLabel(): string
    {
        return Str::headline($this->name);
    }
}
