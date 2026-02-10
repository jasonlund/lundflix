<?php

use App\Casts\SpokenLanguages;
use App\Enums\Language;
use App\Models\Movie;

it('casts JSON array of TMDB objects to Language enums on get', function () {
    $cast = new SpokenLanguages;
    $model = new Movie;

    $json = json_encode([
        ['iso_639_1' => 'en', 'english_name' => 'English', 'name' => 'English'],
        ['iso_639_1' => 'fr', 'english_name' => 'French', 'name' => 'Français'],
    ]);

    expect($cast->get($model, 'spoken_languages', $json, []))
        ->toBe([Language::English, Language::French]);
});

it('returns empty array for null on get', function () {
    $cast = new SpokenLanguages;
    $model = new Movie;

    expect($cast->get($model, 'spoken_languages', null, []))->toBe([]);
});

it('returns empty array for empty string on get', function () {
    $cast = new SpokenLanguages;
    $model = new Movie;

    expect($cast->get($model, 'spoken_languages', '', []))->toBe([]);
});

it('filters out unrecognized ISO codes on get', function () {
    $cast = new SpokenLanguages;
    $model = new Movie;

    $json = json_encode([
        ['iso_639_1' => 'en', 'english_name' => 'English', 'name' => 'English'],
        ['iso_639_1' => 'zz', 'english_name' => 'Unknown', 'name' => 'Unknown'],
        ['iso_639_1' => 'fr', 'english_name' => 'French', 'name' => 'Français'],
    ]);

    expect($cast->get($model, 'spoken_languages', $json, []))
        ->toBe([Language::English, Language::French]);
});

it('converts array of Language enums to TMDB-style JSON on set', function () {
    $cast = new SpokenLanguages;
    $model = new Movie;

    $result = $cast->set($model, 'spoken_languages', [Language::English, Language::French], []);

    expect(json_decode($result, true))->toBe([
        ['iso_639_1' => 'en', 'english_name' => 'English', 'name' => 'English'],
        ['iso_639_1' => 'fr', 'english_name' => 'French', 'name' => 'French'],
    ]);
});

it('returns null for null on set', function () {
    $cast = new SpokenLanguages;
    $model = new Movie;

    expect($cast->set($model, 'spoken_languages', null, []))->toBeNull();
});

it('passes through raw TMDB array on set', function () {
    $cast = new SpokenLanguages;
    $model = new Movie;

    $raw = [
        ['iso_639_1' => 'en', 'english_name' => 'English', 'name' => 'English'],
    ];

    $result = $cast->set($model, 'spoken_languages', $raw, []);

    expect(json_decode($result, true))->toBe($raw);
});

it('passes through JSON string on set', function () {
    $cast = new SpokenLanguages;
    $model = new Movie;

    $json = '[{"iso_639_1":"en","english_name":"English","name":"English"}]';

    expect($cast->set($model, 'spoken_languages', $json, []))->toBe($json);
});
