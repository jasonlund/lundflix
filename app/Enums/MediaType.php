<?php

namespace App\Enums;

use App\Models\Episode;
use App\Models\Movie;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;

enum MediaType: string implements HasLabel
{
    case MOVIE = Movie::class;
    case EPISODE = Episode::class;

    public function getLabel(): string
    {
        return Str::headline($this->name);
    }
}
