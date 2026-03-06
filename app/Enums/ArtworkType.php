<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;

enum ArtworkType: string implements HasColor, HasLabel
{
    case Poster = 'poster';
    case Backdrop = 'backdrop';
    case Logo = 'logo';

    public function getLabel(): string
    {
        return Str::headline($this->name);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Poster => 'success',
            self::Backdrop => 'warning',
            self::Logo => 'info',
        };
    }

    public function defaultSize(): string
    {
        return match ($this) {
            self::Poster => 'w780',
            self::Backdrop => 'w1280',
            self::Logo => 'w500',
        };
    }
}
