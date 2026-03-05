<?php

use App\Enums\ReleaseQuality;

describe('fromReleaseName', function () {
    it('parses WEB-DL releases', function (string $releaseName) {
        expect(ReleaseQuality::fromReleaseName($releaseName))->toBe(ReleaseQuality::WEBDL);
    })->with([
        'standard' => 'The.Movie.2024.1080p.WEB-DL.DD5.1.H.264-GROUP',
        'webdl no hyphen' => 'The.Movie.2024.1080p.WEBDL.x264-GROUP',
        'web.dl with dot' => 'The.Movie.2024.WEB.DL.1080p-GROUP',
        'bare WEB' => 'Marty.Supreme.2025.1080p.WEB.h264-ETHEL',
        'bare WEB 2160p' => 'Marty.Supreme.2025.2160p.WEB.h265-ETHEL',
        'WEB with HDR' => 'Marty.Supreme.2025.HDR.2160p.WEB.h265-ETHEL',
    ]);

    it('parses WEBRip releases', function (string $releaseName) {
        expect(ReleaseQuality::fromReleaseName($releaseName))->toBe(ReleaseQuality::WEBRip);
    })->with([
        'standard' => 'The.Movie.2024.1080p.WEBRip.x264-GROUP',
        'web.rip with dot' => 'The.Movie.2024.WEB.RIP.720p-GROUP',
    ]);

    it('parses streaming source tags as WEBDL', function (string $releaseName) {
        expect(ReleaseQuality::fromReleaseName($releaseName))->toBe(ReleaseQuality::WEBDL);
    })->with([
        'AMZN' => 'The.Movie.2024.1080p.AMZN.H.264-GROUP',
        'ATVP' => 'The.Movie.2024.ATVP.1080p.H265-GROUP',
        'DSNP' => 'The.Movie.2024.DSNP.720p-GROUP',
        'HMAX' => 'The.Movie.2024.HMAX.1080p-GROUP',
        'PCOK' => 'The.Movie.2024.PCOK.1080p-GROUP',
    ]);

    it('parses BluRay releases', function (string $releaseName) {
        expect(ReleaseQuality::fromReleaseName($releaseName))->toBe(ReleaseQuality::BluRay);
    })->with([
        'standard' => 'The.Movie.2024.1080p.BluRay.x264-GROUP',
        'remux' => 'The.Movie.2024.1080p.REMUX.AVC.DTS-HD.MA.5.1-GROUP',
        'bdremux' => 'The.Movie.2024.BDREMUX.1080p-GROUP',
        'complete bluray' => 'The.Movie.2024.COMPLETE.BLURAY-GROUP',
    ]);

    it('parses BDRip releases', function (string $releaseName) {
        expect(ReleaseQuality::fromReleaseName($releaseName))->toBe(ReleaseQuality::BDRip);
    })->with([
        'bdrip' => 'The.Movie.2024.720p.BDRip.x264-GROUP',
        'brrip' => 'The.Movie.2024.BRRip.1080p-GROUP',
    ]);

    it('parses HDTV releases', function (string $releaseName) {
        expect(ReleaseQuality::fromReleaseName($releaseName))->toBe(ReleaseQuality::HDTV);
    })->with([
        'hdtv' => 'The.Movie.2024.HDTV.x264-GROUP',
        'pdtv' => 'The.Movie.2024.PDTV.x264-GROUP',
    ]);

    it('parses DVDRip releases', function () {
        expect(ReleaseQuality::fromReleaseName('The.Movie.2024.DVDRip.x264-GROUP'))
            ->toBe(ReleaseQuality::DVDRip);
    });

    it('parses DVDScr releases', function () {
        expect(ReleaseQuality::fromReleaseName('The.Movie.2024.DVDSCR.x264-GROUP'))
            ->toBe(ReleaseQuality::DVDScr);
    });

    it('parses Screener releases', function () {
        expect(ReleaseQuality::fromReleaseName('The.Movie.2024.SCR.x264-GROUP'))
            ->toBe(ReleaseQuality::Screener);
    });

    it('parses CAM releases', function (string $releaseName) {
        expect(ReleaseQuality::fromReleaseName($releaseName))->toBe(ReleaseQuality::Cam);
    })->with([
        'cam' => 'The.Movie.2024.CAM.H264-GROUP',
        'hdcam' => 'The.Movie.2024.HDCAM.x264-GROUP',
    ]);

    it('parses Telesync releases', function (string $releaseName) {
        expect(ReleaseQuality::fromReleaseName($releaseName))->toBe(ReleaseQuality::Telesync);
    })->with([
        'ts' => 'The.Movie.2024.TS.x264-GROUP',
        'telesync' => 'The.Movie.2024.TELESYNC.x264-GROUP',
        'hdts' => 'The.Movie.2024.HDTS.x264-GROUP',
    ]);

    it('parses Telecine releases', function (string $releaseName) {
        expect(ReleaseQuality::fromReleaseName($releaseName))->toBe(ReleaseQuality::Telecine);
    })->with([
        'tc' => 'The.Movie.2024.TC.x264-GROUP',
        'telecine' => 'The.Movie.2024.TELECINE.x264-GROUP',
    ]);

    it('returns null for unrecognized quality', function () {
        expect(ReleaseQuality::fromReleaseName('Unknown.Release.2024-GROUP'))->toBeNull();
    });

    it('does not false-positive on TS within words', function () {
        expect(ReleaseQuality::fromReleaseName('Monsters.2024.1080p.BluRay.x264-GROUP'))
            ->toBe(ReleaseQuality::BluRay);
    });
});

describe('meetsThreshold', function () {
    it('considers DVDScr and above as meeting threshold', function (ReleaseQuality $quality) {
        expect($quality->meetsThreshold())->toBeTrue();
    })->with([
        'DVDScr' => ReleaseQuality::DVDScr,
        'DVDRip' => ReleaseQuality::DVDRip,
        'HDTV' => ReleaseQuality::HDTV,
        'WEBRip' => ReleaseQuality::WEBRip,
        'WEBDL' => ReleaseQuality::WEBDL,
        'BDRip' => ReleaseQuality::BDRip,
        'BluRay' => ReleaseQuality::BluRay,
    ]);

    it('considers below DVDScr as not meeting threshold', function (ReleaseQuality $quality) {
        expect($quality->meetsThreshold())->toBeFalse();
    })->with([
        'Cam' => ReleaseQuality::Cam,
        'Telesync' => ReleaseQuality::Telesync,
        'Telecine' => ReleaseQuality::Telecine,
        'Screener' => ReleaseQuality::Screener,
    ]);

    it('accepts custom threshold', function () {
        expect(ReleaseQuality::WEBDL->meetsThreshold(ReleaseQuality::WEBDL))->toBeTrue();
        expect(ReleaseQuality::WEBRip->meetsThreshold(ReleaseQuality::WEBDL))->toBeFalse();
    });
});

describe('tags', function () {
    it('returns tag strings for each quality level', function (ReleaseQuality $quality, array $expectedTags) {
        expect($quality->tags())->toBe($expectedTags);
    })->with([
        'Cam' => [ReleaseQuality::Cam, ['CAM', 'HDCAM']],
        'Telesync' => [ReleaseQuality::Telesync, ['TS', 'TELESYNC', 'HDTS']],
        'Telecine' => [ReleaseQuality::Telecine, ['TC', 'TELECINE']],
        'Screener' => [ReleaseQuality::Screener, ['SCR', 'SCREENER']],
        'DVDScr' => [ReleaseQuality::DVDScr, ['DVDSCR', 'DVD.SCR']],
        'DVDRip' => [ReleaseQuality::DVDRip, ['DVDRIP', 'DVD.RIP']],
        'HDTV' => [ReleaseQuality::HDTV, ['HDTV', 'PDTV']],
        'WEBRip' => [ReleaseQuality::WEBRip, ['WEBRIP', 'WEB.RIP']],
        'WEBDL' => [ReleaseQuality::WEBDL, ['WEB-DL', 'WEBDL', 'WEB.DL', 'WEB']],
        'BDRip' => [ReleaseQuality::BDRip, ['BDRIP', 'BRRIP']],
        'BluRay' => [ReleaseQuality::BluRay, ['BLURAY', 'BLU.RAY', 'BDREMUX', 'REMUX', 'COMPLETE.BLURAY']],
    ]);
});

describe('excludedTags', function () {
    it('returns tags for qualities below DVDScr by default', function () {
        $tags = ReleaseQuality::excludedTags();

        expect($tags)
            ->toContain('CAM', 'HDCAM')
            ->toContain('TS', 'TELESYNC', 'HDTS')
            ->toContain('TC', 'TELECINE')
            ->toContain('SCR', 'SCREENER')
            ->not->toContain('DVDSCR')
            ->not->toContain('BLURAY')
            ->not->toContain('WEB-DL');
    });

    it('accepts custom threshold', function () {
        $tags = ReleaseQuality::excludedTags(ReleaseQuality::WEBDL);

        expect($tags)
            ->toContain('CAM', 'HDCAM')
            ->toContain('DVDSCR')
            ->toContain('HDTV')
            ->toContain('WEBRIP')
            ->not->toContain('WEB-DL')
            ->not->toContain('BLURAY');
    });
});
