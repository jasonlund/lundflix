<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;

enum MovieArtworkLevel: string implements HasLabel
{
    case Movie = 'movie';

    public function getLabel(): string
    {
        return Str::headline($this->name);
    }
}
