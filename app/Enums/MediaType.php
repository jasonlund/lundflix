<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;

enum MediaType: string implements HasLabel
{
    case MOVIE = 'App\Models\Movie';
    case EPISODE = 'App\Models\Episode';

    public function getLabel(): string
    {
        return Str::headline($this->name);
    }
}
