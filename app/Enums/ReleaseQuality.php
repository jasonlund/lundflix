<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReleaseQuality: int implements HasLabel
{
    case Cam = 1;
    case Telesync = 2;
    case Telecine = 3;
    case Screener = 4;
    case DVDScr = 5;
    case DVDRip = 6;
    case HDTV = 7;
    case WEBRip = 8;
    case WEBDL = 9;
    case BDRip = 10;
    case BluRay = 11;

    /**
     * Parse the quality tag from a scene release name.
     *
     * Scene release names follow the pattern: Movie.Name.Year.Quality.Source.Codec-Group
     * This method matches known quality tags within the release name.
     */
    public static function fromReleaseName(string $name): ?self
    {
        $upper = strtoupper($name);

        // Order matters — check specific/longer tags before shorter/ambiguous ones
        return match (true) {
            self::contains($upper, ['COMPLETE.BLURAY', 'BDREMUX', 'REMUX']) => self::BluRay,
            self::contains($upper, ['BLURAY', 'BLU.RAY']) => self::BluRay,
            self::contains($upper, ['BDRIP', 'BRRIP']) => self::BDRip,
            self::contains($upper, ['WEB-DL', 'WEBDL', 'WEB.DL']) => self::WEBDL,
            self::contains($upper, ['WEBRIP', 'WEB.RIP']) => self::WEBRip,
            self::containsTag($upper, 'WEB') => self::WEBDL,
            self::containsWebSource($upper) => self::WEBDL,
            self::contains($upper, ['HDTV', 'PDTV']) => self::HDTV,
            self::contains($upper, ['DVDRIP', 'DVD.RIP']) => self::DVDRip,
            self::contains($upper, ['DVDSCR', 'DVD.SCR']) => self::DVDScr,
            self::contains($upper, ['TELECINE']) => self::Telecine,
            self::containsTag($upper, 'TC') => self::Telecine,
            self::contains($upper, ['TELESYNC']) => self::Telesync,
            self::containsTag($upper, 'TS') => self::Telesync,
            self::containsTag($upper, 'HDTS') => self::Telesync,
            self::contains($upper, ['HDCAM']) => self::Cam,
            self::containsTag($upper, 'CAM') => self::Cam,
            self::containsTag($upper, 'SCR') => self::Screener,
            self::contains($upper, ['SCREENER']) => self::Screener,
            default => null,
        };
    }

    /**
     * PreDB tag strings associated with this quality level.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return match ($this) {
            self::Cam => ['CAM', 'HDCAM'],
            self::Telesync => ['TS', 'TELESYNC', 'HDTS'],
            self::Telecine => ['TC', 'TELECINE'],
            self::Screener => ['SCR', 'SCREENER'],
            self::DVDScr => ['DVDSCR', 'DVD.SCR'],
            self::DVDRip => ['DVDRIP', 'DVD.RIP'],
            self::HDTV => ['HDTV', 'PDTV'],
            self::WEBRip => ['WEBRIP', 'WEB.RIP'],
            self::WEBDL => ['WEB-DL', 'WEBDL', 'WEB.DL', 'WEB'],
            self::BDRip => ['BDRIP', 'BRRIP'],
            self::BluRay => ['BLURAY', 'BLU.RAY', 'BDREMUX', 'REMUX', 'COMPLETE.BLURAY'],
        };
    }

    /**
     * Collect all tags for qualities below the given threshold (for API exclusion).
     *
     * @return array<int, string>
     */
    public static function excludedTags(self $threshold = self::DVDScr): array
    {
        $tags = [];

        foreach (self::cases() as $case) {
            if (! $case->meetsThreshold($threshold)) {
                $tags = array_merge($tags, $case->tags());
            }
        }

        return $tags;
    }

    public function meetsThreshold(self $threshold = self::DVDScr): bool
    {
        return $this->value >= $threshold->value;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Cam => 'CAM',
            self::Telesync => 'Telesync',
            self::Telecine => 'Telecine',
            self::Screener => 'Screener',
            self::DVDScr => 'DVD Screener',
            self::DVDRip => 'DVD Rip',
            self::HDTV => 'HDTV',
            self::WEBRip => 'WEB Rip',
            self::WEBDL => 'WEB-DL',
            self::BDRip => 'BD Rip',
            self::BluRay => 'Blu-Ray',
        };
    }

    /**
     * Check if the release name contains any of the given tags as dot-delimited segments.
     *
     * @param  array<int, string>  $tags
     */
    private static function contains(string $upper, array $tags): bool
    {
        foreach ($tags as $tag) {
            if (str_contains($upper, $tag)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a short tag appears as a dot-delimited segment to avoid false positives.
     * For example, "TS" should match ".TS." but not "MONSTERS" or "ARTS".
     */
    private static function containsTag(string $upper, string $tag): bool
    {
        return (bool) preg_match('/(?:^|\.)'.preg_quote($tag, '/').'(?:\.|$|-)/i', $upper);
    }

    /**
     * Check for streaming service source tags that imply WEB-DL quality.
     */
    private static function containsWebSource(string $upper): bool
    {
        $sources = ['AMZN', 'ATVP', 'DSNP', 'HMAX', 'PCOK', 'PMTP', 'STAN'];

        foreach ($sources as $source) {
            if (self::containsTag($upper, $source)) {
                return true;
            }
        }

        return false;
    }
}
