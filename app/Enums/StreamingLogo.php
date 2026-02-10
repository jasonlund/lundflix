<?php

namespace App\Enums;

use Illuminate\Support\Facades\Vite;

/**
 * Streaming service logos mapped by TVMaze web channel ID.
 *
 * Source: https://github.com/tv-logo/tv-logos
 */
enum StreamingLogo: int
{
    case Netflix = 1;
    case Hulu = 2;
    case PrimeVideo = 3;
    case YouTube = 21;
    case BbcIplayer = 26;
    case ParamountPlus = 107;
    case DiscoveryPlus = 173;
    case EspnPlus = 265;
    case DisneyPlus = 287;
    case AppleTvPlus = 310;
    case HboMax = 329;
    case Peacock = 347;
    case AmcPlus = 453;

    public function file(): string
    {
        return match ($this) {
            self::Netflix => 'netflix.png',
            self::Hulu => 'hulu.png',
            self::PrimeVideo => 'prime-video.png',
            self::YouTube => 'youtube.png',
            self::BbcIplayer => 'bbc-iplayer.png',
            self::ParamountPlus => 'paramount-plus.png',
            self::DiscoveryPlus => 'discovery-plus.png',
            self::EspnPlus => 'espn-plus.png',
            self::DisneyPlus => 'disney-plus.png',
            self::AppleTvPlus => 'apple-tv-plus.png',
            self::HboMax => 'hbo-max.png',
            self::Peacock => 'peacock.png',
            self::AmcPlus => 'amc-plus-vod-us.png',
        };
    }

    public function source(): string
    {
        return match ($this) {
            self::AmcPlus => "misc/vod/{$this->file()}",
            default => "misc/media/{$this->file()}",
        };
    }

    public function url(): string
    {
        return Vite::asset("resources/images/logos/streaming/{$this->file()}");
    }
}
