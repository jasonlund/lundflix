<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum MovieArtwork: string
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
