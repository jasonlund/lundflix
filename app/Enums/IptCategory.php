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

    public function label(): string
    {
        return match ($this) {
            self::Movie3d => '3D',
            self::Movie480p => '480p',
            self::Movie4k => '4K',
            self::MovieBdR => 'BD-R',
            self::MovieBdRip => 'BD Rip',
            self::MovieCam => 'Cam',
            self::MovieDvdR => 'DVD-R',
            self::MovieHdBluray => 'HD Blu-ray',
            self::MovieKids => 'Kids',
            self::MovieMp4 => 'MP4',
            self::MovieNonEnglish => 'Non-English',
            self::MoviePacks => 'Packs',
            self::MovieWebDl => 'Web-DL',
            self::MovieX265 => 'x265',
            self::MovieXvid => 'XviD',
            self::Documentaries => 'Documentaries',
            self::Sports => 'Sports',
            self::Tv480p => '480p',
            self::TvBd => 'BD',
            self::TvDvdR => 'DVD-R',
            self::TvDvdRip => 'DVD Rip',
            self::TvMobile => 'Mobile',
            self::TvNonEnglish => 'Non-English',
            self::TvPacks => 'Packs',
            self::TvPacksNonEnglish => 'Packs (Non-English)',
            self::TvSdX264 => 'SD x264',
            self::TvWebDl => 'Web-DL',
            self::TvX264 => 'x264',
            self::TvX265 => 'x265',
            self::TvXvid => 'XviD',
        };
    }

    /** @return list<self> */
    public static function movieCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $case): bool => str_starts_with($case->name, 'Movie'),
        ));
    }

    /** @return list<self> */
    public static function tvCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $case): bool => ! str_starts_with($case->name, 'Movie'),
        ));
    }

    /**
     * Get options array for Filament form components.
     *
     * @param  list<self>  $cases
     * @return array<int, string>
     */
    public static function options(array $cases): array
    {
        $options = [];
        foreach ($cases as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /** @return list<int> */
    public static function defaultMovieValues(): array
    {
        return [self::MovieX265->value];
    }

    /** @return list<int> */
    public static function defaultTvValues(): array
    {
        return [self::TvPacks->value, self::TvX265->value];
    }

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
