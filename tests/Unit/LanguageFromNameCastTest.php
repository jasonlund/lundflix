<?php

use App\Casts\LanguageFromName;
use App\Enums\Language;
use App\Models\Show;

it('casts full name to Language enum on get', function () {
    $cast = new LanguageFromName;
    $model = new Show;

    expect($cast->get($model, 'language', 'English', []))->toBe(Language::English)
        ->and($cast->get($model, 'language', 'French', []))->toBe(Language::French)
        ->and($cast->get($model, 'language', 'Scottish Gaelic', []))->toBe(Language::ScottishGaelic);
});

it('returns null for null value on get', function () {
    $cast = new LanguageFromName;
    $model = new Show;

    expect($cast->get($model, 'language', null, []))->toBeNull();
});

it('returns null for empty string on get', function () {
    $cast = new LanguageFromName;
    $model = new Show;

    expect($cast->get($model, 'language', '', []))->toBeNull();
});

it('returns null for unrecognized language name on get', function () {
    $cast = new LanguageFromName;
    $model = new Show;

    expect($cast->get($model, 'language', 'Klingon', []))->toBeNull();
});

it('converts Language enum to full name on set', function () {
    $cast = new LanguageFromName;
    $model = new Show;

    expect($cast->set($model, 'language', Language::English, []))->toBe('English')
        ->and($cast->set($model, 'language', Language::ScottishGaelic, []))->toBe('Scottish Gaelic');
});

it('returns null for null value on set', function () {
    $cast = new LanguageFromName;
    $model = new Show;

    expect($cast->set($model, 'language', null, []))->toBeNull();
});

it('passes through raw string on set', function () {
    $cast = new LanguageFromName;
    $model = new Show;

    expect($cast->set($model, 'language', 'English', []))->toBe('English')
        ->and($cast->set($model, 'language', 'Klingon', []))->toBe('Klingon');
});
