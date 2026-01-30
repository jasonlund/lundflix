<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum TvArtwork: string
{
    case HdClearLogo = 'hdtvlogo';
    case Poster = 'tvposter';
    case SeasonPoster = 'seasonposter';
    case HdClearArt = 'hdclearart';
    case CharacterArt = 'characterart';
    case TvThumbs = 'tvthumb';
    case SeasonThumbs = 'seasonthumb';
    case Background = 'showbackground';
    case Banner = 'tvbanner';
    case Background4k = 'showbackground-4k';
    case SeasonBanner = 'seasonbanner';

    public function level(): TvArtworkLevel
    {
        return match ($this) {
            self::SeasonPoster, self::SeasonThumbs, self::SeasonBanner => TvArtworkLevel::Season,
            default => TvArtworkLevel::Show,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::HdClearLogo => 'HD ClearLOGO',
            self::HdClearArt => 'HD ClearART',
            self::CharacterArt => 'CharacterART',
            self::TvThumbs => 'TV Thumbs',
            self::SeasonThumbs => 'Season Thumbs',
            self::Background4k => '4K Background',
            default => Str::headline($this->name),
        };
    }
}
