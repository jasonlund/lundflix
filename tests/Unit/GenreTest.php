<?php

use App\Enums\Genre;

it('has 37 genre cases', function () {
    expect(Genre::cases())->toHaveCount(37);
});

it('returns correct labels for standard genres', function (Genre $genre, string $expected) {
    expect($genre->label())->toBe($expected);
})->with([
    [Genre::Action, 'Action'],
    [Genre::Comedy, 'Comedy'],
    [Genre::Drama, 'Drama'],
    [Genre::Horror, 'Horror'],
    [Genre::Romance, 'Romance'],
    [Genre::Thriller, 'Thriller'],
    [Genre::Western, 'Western'],
]);

it('returns correct labels for special-cased genres', function (Genre $genre, string $expected) {
    expect($genre->label())->toBe($expected);
})->with([
    [Genre::DIY, 'DIY'],
    [Genre::FilmNoir, 'Film Noir'],
    [Genre::GameShow, 'Game Show'],
    [Genre::RealityTV, 'Reality TV'],
    [Genre::SciFi, 'Sci-Fi'],
    [Genre::TalkShow, 'Talk Show'],
]);

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
