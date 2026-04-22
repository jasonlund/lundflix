<?php

use App\Support\PlexLibraryFormatter;

beforeEach(function () {
    $this->formatter = new PlexLibraryFormatter;
});

function movieItem(string $title, ?int $year = 2024): array
{
    return [
        'media_type' => 'movie',
        'title' => $title,
        'year' => $year,
        'show_title' => null,
        'season' => null,
        'episode_number' => null,
    ];
}

function episodeItem(string $showTitle, int $season, int $episodeNumber, string $title = 'Episode'): array
{
    return [
        'media_type' => 'episode',
        'title' => $title,
        'year' => null,
        'show_title' => $showTitle,
        'season' => $season,
        'episode_number' => $episodeNumber,
    ];
}

it('formats a single movie', function () {
    $result = $this->formatter->format('My Server', collect([
        movieItem('Inception', 2010),
    ]));

    expect($result)->toBe("*New on My Server:*\nInception (2010)");
});

it('formats a movie without year', function () {
    $result = $this->formatter->format('My Server', collect([
        movieItem('Unknown Movie', null),
    ]));

    expect($result)->toBe("*New on My Server:*\nUnknown Movie");
});

it('formats multiple movies sorted by title', function () {
    $result = $this->formatter->format('My Server', collect([
        movieItem('The Matrix', 1999),
        movieItem('Inception', 2010),
    ]));

    expect($result)->toBe("*New on My Server:*\nInception (2010)\nThe Matrix (1999)");
});

it('formats a single episode', function () {
    $result = $this->formatter->format('My Server', collect([
        episodeItem('Breaking Bad', 1, 5, 'Gray Matter'),
    ]));

    expect($result)->toBe("*New on My Server:*\nBreaking Bad S01E05");
});

it('formats consecutive episodes as a run', function () {
    $result = $this->formatter->format('My Server', collect([
        episodeItem('Breaking Bad', 1, 1),
        episodeItem('Breaking Bad', 1, 2),
        episodeItem('Breaking Bad', 1, 3),
    ]));

    expect($result)->toBe("*New on My Server:*\nBreaking Bad S01E01-E03");
});

it('formats non-consecutive episodes with gap detection', function () {
    $result = $this->formatter->format('My Server', collect([
        episodeItem('Friends', 1, 1),
        episodeItem('Friends', 1, 3),
        episodeItem('Friends', 1, 4),
        episodeItem('Friends', 1, 5),
    ]));

    expect($result)->toBe("*New on My Server:*\nFriends S01E01, S01E03-E05");
});

it('formats episodes across multiple seasons', function () {
    $result = $this->formatter->format('My Server', collect([
        episodeItem('Lost', 1, 1),
        episodeItem('Lost', 1, 2),
        episodeItem('Lost', 2, 1),
    ]));

    expect($result)->toBe("*New on My Server:*\nLost S01E01-E02, S02E01");
});

it('formats multiple shows each on their own line', function () {
    $result = $this->formatter->format('My Server', collect([
        episodeItem('Breaking Bad', 1, 1),
        episodeItem('Lost', 2, 5),
    ]));

    expect($result)->toBe("*New on My Server:*\nBreaking Bad S01E01\nLost S02E05");
});

it('formats mixed movies and episodes', function () {
    $result = $this->formatter->format('My Server', collect([
        movieItem('Inception', 2010),
        episodeItem('Breaking Bad', 1, 1),
        episodeItem('Breaking Bad', 1, 2),
    ]));

    expect($result)->toBe("*New on My Server:*\nInception (2010)\nBreaking Bad S01E01-E02");
});

it('uses Plex as fallback server name', function () {
    $result = $this->formatter->format(null, collect([
        movieItem('Inception', 2010),
    ]));

    expect($result)->toStartWith('*New on Plex:*');
});
