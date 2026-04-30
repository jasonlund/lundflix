<?php

use App\Support\PlexLibraryFormatter;

beforeEach(function () {
    $this->formatter = new PlexLibraryFormatter;
    $this->linkedFormatter = new PlexLibraryFormatter('test-client-id');
});

function movieItem(string $title, ?int $year = 2024, string $ratingKey = ''): array
{
    return [
        'media_type' => 'movie',
        'title' => $title,
        'year' => $year,
        'rating_key' => $ratingKey,
        'show_title' => null,
        'season' => null,
        'episode_number' => null,
    ];
}

function episodeItem(
    string $showTitle,
    int $season,
    int $episodeNumber,
    string $title = 'Episode',
    string $ratingKey = '',
    string $parentRatingKey = '',
    string $grandparentRatingKey = '',
): array {
    return [
        'media_type' => 'episode',
        'title' => $title,
        'year' => null,
        'show_title' => $showTitle,
        'season' => $season,
        'episode_number' => $episodeNumber,
        'rating_key' => $ratingKey,
        'parent_rating_key' => $parentRatingKey,
        'grandparent_rating_key' => $grandparentRatingKey,
    ];
}

function plexLink(string $ratingKey): string
{
    return "https://app.plex.tv/desktop/#!/server/test-client-id/details?key=%2Flibrary%2Fmetadata%2F{$ratingKey}";
}

it('formats a single movie', function () {
    $result = $this->formatter->format(collect([
        movieItem('Inception', 2010),
    ]));

    expect($result)->toBe('Inception (2010)');
});

it('formats a movie without year', function () {
    $result = $this->formatter->format(collect([
        movieItem('Unknown Movie', null),
    ]));

    expect($result)->toBe('Unknown Movie');
});

it('formats multiple movies sorted by title', function () {
    $result = $this->formatter->format(collect([
        movieItem('The Matrix', 1999),
        movieItem('Inception', 2010),
    ]));

    expect($result)->toBe("Inception (2010)\nThe Matrix (1999)");
});

it('formats a single episode', function () {
    $result = $this->formatter->format(collect([
        episodeItem('Breaking Bad', 1, 5, 'Gray Matter'),
    ]));

    expect($result)->toBe('Breaking Bad S01E05');
});

it('formats consecutive episodes as a run', function () {
    $result = $this->formatter->format(collect([
        episodeItem('Breaking Bad', 1, 1),
        episodeItem('Breaking Bad', 1, 2),
        episodeItem('Breaking Bad', 1, 3),
    ]));

    expect($result)->toBe('Breaking Bad S01E01-E03');
});

it('formats non-consecutive episodes with gap detection', function () {
    $result = $this->formatter->format(collect([
        episodeItem('Friends', 1, 1),
        episodeItem('Friends', 1, 3),
        episodeItem('Friends', 1, 4),
        episodeItem('Friends', 1, 5),
    ]));

    expect($result)->toBe('Friends S01E01, S01E03-E05');
});

it('formats episodes across multiple seasons', function () {
    $result = $this->formatter->format(collect([
        episodeItem('Lost', 1, 1),
        episodeItem('Lost', 1, 2),
        episodeItem('Lost', 2, 1),
    ]));

    expect($result)->toBe('Lost S01E01-E02, S02E01');
});

it('formats multiple shows each on their own line', function () {
    $result = $this->formatter->format(collect([
        episodeItem('Breaking Bad', 1, 1),
        episodeItem('Lost', 2, 5),
    ]));

    expect($result)->toBe("Breaking Bad S01E01\nLost S02E05");
});

it('formats mixed movies and episodes', function () {
    $result = $this->formatter->format(collect([
        movieItem('Inception', 2010),
        episodeItem('Breaking Bad', 1, 1),
        episodeItem('Breaking Bad', 1, 2),
    ]));

    expect($result)->toBe("Inception (2010)\nBreaking Bad S01E01-E02");
});

// --- Plex link tests ---

it('appends plex link to movie when client identifier is set', function () {
    $result = $this->linkedFormatter->format(collect([
        movieItem('Inception', 2010, '100'),
    ]));

    expect($result)->toBe('Inception (2010) <'.plexLink('100').'|↗️>');
});

it('omits plex link from movie when rating key is empty', function () {
    $result = $this->linkedFormatter->format(collect([
        movieItem('Inception', 2010, ''),
    ]));

    expect($result)->toBe('Inception (2010)');
});

it('links single episode to the episode', function () {
    $result = $this->linkedFormatter->format(collect([
        episodeItem('Breaking Bad', 1, 5, 'Gray Matter', ratingKey: '200', parentRatingKey: '55', grandparentRatingKey: '50'),
    ]));

    expect($result)->toBe('Breaking Bad S01E05 <'.plexLink('200').'|↗️>');
});

it('links multiple episodes in one season to the season', function () {
    $result = $this->linkedFormatter->format(collect([
        episodeItem('Breaking Bad', 1, 1, ratingKey: '200', parentRatingKey: '55', grandparentRatingKey: '50'),
        episodeItem('Breaking Bad', 1, 2, ratingKey: '201', parentRatingKey: '55', grandparentRatingKey: '50'),
        episodeItem('Breaking Bad', 1, 3, ratingKey: '202', parentRatingKey: '55', grandparentRatingKey: '50'),
    ]));

    expect($result)->toBe('Breaking Bad S01E01-E03 <'.plexLink('55').'|↗️>');
});

it('links episodes across multiple seasons to the show', function () {
    $result = $this->linkedFormatter->format(collect([
        episodeItem('Lost', 1, 1, ratingKey: '300', parentRatingKey: '60', grandparentRatingKey: '40'),
        episodeItem('Lost', 1, 2, ratingKey: '301', parentRatingKey: '60', grandparentRatingKey: '40'),
        episodeItem('Lost', 2, 1, ratingKey: '302', parentRatingKey: '61', grandparentRatingKey: '40'),
    ]));

    expect($result)->toBe('Lost S01E01-E02, S02E01 <'.plexLink('40').'|↗️>');
});

it('omits plex link from episodes when rating key is empty', function () {
    $result = $this->linkedFormatter->format(collect([
        episodeItem('Breaking Bad', 1, 1, ratingKey: '', parentRatingKey: '', grandparentRatingKey: ''),
    ]));

    expect($result)->toBe('Breaking Bad S01E01');
});

it('omits plex links when no client identifier', function () {
    $result = $this->formatter->format(collect([
        movieItem('Inception', 2010, '100'),
        episodeItem('Breaking Bad', 1, 1, ratingKey: '200', parentRatingKey: '55', grandparentRatingKey: '50'),
    ]));

    expect($result)->toBe("Inception (2010)\nBreaking Bad S01E01");
});
