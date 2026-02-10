<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;

enum TvArtwork: string implements HasLabel
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

    /**
     * Get all artwork types for a given level.
     *
     * @return list<self>
     */
    public static function forLevel(TvArtworkLevel $level): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $artwork) => $artwork->level() === $level
        ));
    }

    /**
     * Get the API values for all artwork types at a given level.
     *
     * @return list<string>
     */
    public static function valuesForLevel(TvArtworkLevel $level): array
    {
        return array_map(
            fn (self $artwork) => $artwork->value,
            self::forLevel($level)
        );
    }
}
