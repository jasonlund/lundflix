<?php

namespace App\Enums;

enum IptCategory: int
{
    // Movie categories
    case Movie3d = 87;
    case Movie480p = 77;
    case Movie4k = 101;
    case MovieBdR = 89;
    case MovieBdRip = 90;
    case MovieCam = 96;
    case MovieDvdR = 6;
    case MovieHdBluray = 48;
    case MovieKids = 54;
    case MovieMp4 = 62;
    case MovieNonEnglish = 38;
    case MoviePacks = 68;
    case MovieWebDl = 20;
    case MovieX265 = 100;
    case MovieXvid = 7;

    // TV categories
    case Documentaries = 26;
    case Sports = 55;
    case Tv480p = 78;
    case TvBd = 23;
    case TvDvdR = 24;
    case TvDvdRip = 25;
    case TvMobile = 66;
    case TvNonEnglish = 82;
    case TvPacks = 65;
    case TvPacksNonEnglish = 83;
    case TvSdX264 = 79;
    case TvWebDl = 22;
    case TvX264 = 5;
    case TvX265 = 99;
    case TvXvid = 4;

    /**
     * Build the query string portion for the given categories.
     *
     * @param  list<self>  $categories
     */
    public static function queryString(array $categories): string
    {
        return implode('&', array_map(
            fn (self $category): string => "{$category->value}=",
            $categories,
        ));
    }
}
