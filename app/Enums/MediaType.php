<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum MediaType: string
{
    case MOVIE = 'App\Models\Movie';
    case EPISODE = 'App\Models\Episode';

    public function getLabel(): string
    {
        return Str::headline($this->name);
    }
}
