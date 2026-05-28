<?php

use App\Services\Torrent\Support\PackNameParser;

it('parses single-season tokens', function (string $name, int $season) {
    expect(PackNameParser::parse($name))->toBe(['type' => 'single', 'start' => $season, 'end' => $season]);
})->with([
    ['Show.S03.1080p.WEB-DL', 3],
    ['Show S3 720p', 3],
    ['Show.S12.HEVC.x265', 12],
    ['Some Show Season 4 1080p', 4],
    ['Show.Season.7.Complete', 7],
]);

it('parses season-range tokens', function (string $name, int $start, int $end) {
    expect(PackNameParser::parse($name))->toBe(['type' => 'range', 'start' => $start, 'end' => $end]);
})->with([
    ['Survivor.S01-S38.1080p', 1, 38],
    ['Survivor S01–S38', 1, 38],
    ['The.Office.S01.S09.Complete', 1, 9],
    ['Show Seasons 1-5 1080p', 1, 5],
    ['Show Seasons 2 to 4 WEB-DL', 2, 4],
    ['Show.S05-S08.x265', 5, 8],
]);

it('parses complete-series tokens', function (string $name) {
    expect(PackNameParser::parse($name))->toBe(['type' => 'complete', 'start' => null, 'end' => null]);
})->with([
    ['The Wire Complete Series 1080p',
    ],
    ['Friends Complete Collection',
    ],
    ['Show.Complete.Seasons.1080p',
    ],
]);

it('returns null for non-season names', function (string $name) {
    expect(PackNameParser::parse($name))->toBeNull();
})->with([
    ['Some.Movie.2024.1080p.WEB-DL'],
    ['Random.Name.x265'],
    [''],
]);

it('prefers range over single when both could match', function () {
    expect(PackNameParser::parse('Show.S01-S05.1080p'))
        ->toBe(['type' => 'range', 'start' => 1, 'end' => 5]);
});

it('prefers range over complete when both present', function () {
    expect(PackNameParser::parse('Complete Series Show S01-S05'))
        ->toBe(['type' => 'range', 'start' => 1, 'end' => 5]);
});
