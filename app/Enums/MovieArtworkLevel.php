<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum MovieArtworkLevel: string
{
    case Movie = 'movie';

    public function getLabel(): string
    {
        return Str::headline($this->name);
    }
}
