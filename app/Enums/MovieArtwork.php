<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;

enum MovieArtwork: string implements HasColor, HasLabel
{
    case HdClearLogo = 'hdmovielogo';
    case Poster = 'movieposter';
    case HdClearArt = 'hdmovieclearart';
    case CdArt = 'moviedisc';
    case Background = 'moviebackground';
    case Background4k = 'moviebackground-4k';
    case Banner = 'moviebanner';
    case MovieThumbs = 'moviethumb';

    public function level(): MovieArtworkLevel
    {
        return MovieArtworkLevel::Movie;
    }

    public function getColor(): string
    {
        return match ($this) {
            self::HdClearLogo, self::HdClearArt => 'info',
            self::Poster => 'success',
            self::Background, self::Background4k => 'warning',
            self::CdArt => 'gray',
            default => 'primary',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::HdClearLogo => 'HD ClearLOGO',
            self::HdClearArt => 'HD ClearART',
            self::CdArt => 'cdART',
            self::Background4k => '4K Background',
            default => Str::headline($this->name),
        };
    }
}
