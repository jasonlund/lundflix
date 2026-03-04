<?php

use App\Enums\Genre;

it('has 37 genre cases', function () {
    expect(Genre::cases())->toHaveCount(37);
});

it('resolves genres from string case-insensitively', function (string $input, Genre $expected) {
    expect(Genre::tryFromString($input))->toBe($expected);
})->with([
    ['action', Genre::Action],
    ['Action', Genre::Action],
    ['ACTION', Genre::Action],
    ['sci-fi', Genre::SciFi],
    ['Sci-Fi', Genre::SciFi],
    ['SCI-FI', Genre::SciFi],
]);

it('normalizes duplicate genre names to canonical form', function (string $input, Genre $expected) {
    expect(Genre::tryFromString($input))->toBe($expected);
})->with([
    ['Science-Fiction', Genre::SciFi],
    ['science-fiction', Genre::SciFi],
    ['SCIENCE-FICTION', Genre::SciFi],
    ['Sports', Genre::Sport],
    ['sports', Genre::Sport],
    ['SPORTS', Genre::Sport],
]);

it('returns null for unknown genres in tryFromString', function (string $input) {
    expect(Genre::tryFromString($input))->toBeNull();
})->with([
    'empty string' => '',
    'unknown genre' => 'unknown-genre',
    'gibberish' => 'xyzabc',
    'partial match' => 'act',
]);

it('returns label for known genres via labelFor', function (string $input, string $expected) {
    expect(Genre::labelFor($input))->toBe($expected);
})->with([
    ['action', 'Action'],
    ['Sci-Fi', 'Sci-Fi'],
    ['Science-Fiction', 'Sci-Fi'],
    ['Sports', 'Sport'],
    ['film-noir', 'Film Noir'],
    ['reality-tv', 'Reality TV'],
]);

it('title-cases unknown genres via labelFor', function (string $input, string $expected) {
    expect(Genre::labelFor($input))->toBe($expected);
})->with([
    ['unknown-genre', 'Unknown Genre'],
    ['weird_category', 'Weird Category'],
    ['something', 'Something'],
    ['multi-word-thing', 'Multi Word Thing'],
]);

it('returns icon for known genres via iconFor', function (string $input, string $expected) {
    expect(Genre::iconFor($input))->toBe($expected);
})->with([
    ['action', 'swords'],
    ['Drama', 'drama'],
    ['Sci-Fi', 'rocket'],
    ['Science-Fiction', 'rocket'],
    ['Sports', 'trophy'],
]);

it('returns tag icon for unknown genres via iconFor', function (string $input) {
    expect(Genre::iconFor($input))->toBe('tag');
})->with([
    'empty string' => '',
    'unknown genre' => 'unknown-genre',
]);
