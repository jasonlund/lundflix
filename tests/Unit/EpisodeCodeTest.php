<?php

use App\Support\EpisodeCode;

it('generates regular episode code with zero padding', function () {
    expect(EpisodeCode::generate(1, 5))->toBe('s01e05')
        ->and(EpisodeCode::generate(10, 15))->toBe('s10e15')
        ->and(EpisodeCode::generate(1, 1))->toBe('s01e01')
        ->and(EpisodeCode::generate(24, 1))->toBe('s24e01');
});

it('generates special episode code with zero padding', function () {
    expect(EpisodeCode::generate(1, 1, isSpecial: true))->toBe('s01s01')
        ->and(EpisodeCode::generate(24, 1, isSpecial: true))->toBe('s24s01')
        ->and(EpisodeCode::generate(24, 2, isSpecial: true))->toBe('s24s02');
});

it('parses valid regular episode codes', function () {
    expect(EpisodeCode::parse('s01e05'))->toBe(['season' => 1, 'number' => 5, 'is_special' => false])
        ->and(EpisodeCode::parse('s10e15'))->toBe(['season' => 10, 'number' => 15, 'is_special' => false])
        ->and(EpisodeCode::parse('S24E01'))->toBe(['season' => 24, 'number' => 1, 'is_special' => false]);
});

it('parses valid special episode codes', function () {
    expect(EpisodeCode::parse('s01s01'))->toBe(['season' => 1, 'number' => 1, 'is_special' => true])
        ->and(EpisodeCode::parse('s24s01'))->toBe(['season' => 24, 'number' => 1, 'is_special' => true])
        ->and(EpisodeCode::parse('S24S02'))->toBe(['season' => 24, 'number' => 2, 'is_special' => true]);
});

it('parses codes case-insensitively', function () {
    expect(EpisodeCode::parse('S01E05'))->toBe(['season' => 1, 'number' => 5, 'is_special' => false])
        ->and(EpisodeCode::parse('s01E05'))->toBe(['season' => 1, 'number' => 5, 'is_special' => false])
        ->and(EpisodeCode::parse('S01S01'))->toBe(['season' => 1, 'number' => 1, 'is_special' => true]);
});

it('throws exception for invalid episode codes', function (string $code) {
    EpisodeCode::parse($code);
})->with([
    'empty string' => '',
    'missing prefix' => '01e05',
    'invalid format' => 'episode1',
    'missing episode number' => 's01e',
    'missing season number' => 'se05',
    'wrong separator' => 's01x05',
])->throws(InvalidArgumentException::class);

it('handles large season and episode numbers', function () {
    expect(EpisodeCode::generate(100, 200))->toBe('s100e200')
        ->and(EpisodeCode::parse('s100e200'))->toBe(['season' => 100, 'number' => 200, 'is_special' => false]);
});

it('sorts episodes with dates by date ascending', function () {
    $episodes = [
        ['airdate' => '2024-03-15', 'tvmaze_id' => 1],
        ['airdate' => '2024-01-10', 'tvmaze_id' => 2],
        ['airdate' => '2024-02-20', 'tvmaze_id' => 3],
    ];

    usort($episodes, EpisodeCode::compareForSorting(...));

    expect($episodes[0]['airdate'])->toBe('2024-01-10')
        ->and($episodes[1]['airdate'])->toBe('2024-02-20')
        ->and($episodes[2]['airdate'])->toBe('2024-03-15');
});

it('sorts episodes with same date by tvmaze_id', function () {
    $episodes = [
        ['airdate' => '2024-01-10', 'tvmaze_id' => 300],
        ['airdate' => '2024-01-10', 'tvmaze_id' => 100],
        ['airdate' => '2024-01-10', 'tvmaze_id' => 200],
    ];

    usort($episodes, EpisodeCode::compareForSorting(...));

    expect($episodes[0]['tvmaze_id'])->toBe(100)
        ->and($episodes[1]['tvmaze_id'])->toBe(200)
        ->and($episodes[2]['tvmaze_id'])->toBe(300);
});

it('sorts dated episodes before undated episodes', function () {
    $episodes = [
        ['airdate' => null, 'tvmaze_id' => 1],
        ['airdate' => '2024-01-10', 'tvmaze_id' => 2],
        ['airdate' => null, 'tvmaze_id' => 3],
    ];

    usort($episodes, EpisodeCode::compareForSorting(...));

    expect($episodes[0]['tvmaze_id'])->toBe(2)
        ->and($episodes[1]['tvmaze_id'])->toBe(1)
        ->and($episodes[2]['tvmaze_id'])->toBe(3);
});

it('sorts undated episodes by tvmaze_id', function () {
    $episodes = [
        ['airdate' => null, 'tvmaze_id' => 300],
        ['airdate' => null, 'tvmaze_id' => 100],
        ['airdate' => null, 'tvmaze_id' => 200],
    ];

    usort($episodes, EpisodeCode::compareForSorting(...));

    expect($episodes[0]['tvmaze_id'])->toBe(100)
        ->and($episodes[1]['tvmaze_id'])->toBe(200)
        ->and($episodes[2]['tvmaze_id'])->toBe(300);
});

it('falls back to id when tvmaze_id is not present', function () {
    $episodes = [
        ['airdate' => null, 'id' => 300],
        ['airdate' => null, 'id' => 100],
        ['airdate' => null, 'id' => 200],
    ];

    usort($episodes, EpisodeCode::compareForSorting(...));

    expect($episodes[0]['id'])->toBe(100)
        ->and($episodes[1]['id'])->toBe(200)
        ->and($episodes[2]['id'])->toBe(300);
});
