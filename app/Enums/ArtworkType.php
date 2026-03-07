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

    public const VALID_SIZES = ['w92', 'w154', 'w185', 'w200', 'w300', 'w342', 'w500', 'w780', 'w1280', 'original'];

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
