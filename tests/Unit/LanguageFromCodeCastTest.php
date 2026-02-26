<?php

use App\Casts\LanguageFromCode;
use App\Enums\Language;
use App\Models\Movie;
use Illuminate\Support\Facades\Log;

it('casts ISO code to Language enum on get', function () {
    $cast = new LanguageFromCode;
    $model = new Movie;

    expect($cast->get($model, 'original_language', 'en', []))->toBe(Language::English)
        ->and($cast->get($model, 'original_language', 'fr', []))->toBe(Language::French)
        ->and($cast->get($model, 'original_language', 'hz', []))->toBe(Language::Herero);
});

it('returns null for null value on get', function () {
    $cast = new LanguageFromCode;
    $model = new Movie;

    expect($cast->get($model, 'original_language', null, []))->toBeNull();
});

it('returns null for empty string on get', function () {
    $cast = new LanguageFromCode;
    $model = new Movie;

    expect($cast->get($model, 'original_language', '', []))->toBeNull();
});

it('returns null and logs warning for unknown code on get', function () {
    Log::spy();

    $cast = new LanguageFromCode;
    $model = new Movie;
    $model->id = 999;

    $result = $cast->get($model, 'original_language', 'zz', []);

    expect($result)->toBeNull();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context) => $message === 'Unknown ISO 639-1 language code encountered'
            && $context['code'] === 'zz'
            && $context['model'] === Movie::class
            && $context['id'] === 999
        )
        ->once();
});

it('converts Language enum to ISO code on set', function () {
    $cast = new LanguageFromCode;
    $model = new Movie;

    expect($cast->set($model, 'original_language', Language::English, []))->toBe('en')
        ->and($cast->set($model, 'original_language', Language::Herero, []))->toBe('hz');
});

it('returns null for null value on set', function () {
    $cast = new LanguageFromCode;
    $model = new Movie;

    expect($cast->set($model, 'original_language', null, []))->toBeNull();
});

it('passes through raw string on set', function () {
    $cast = new LanguageFromCode;
    $model = new Movie;

    expect($cast->set($model, 'original_language', 'en', []))->toBe('en')
        ->and($cast->set($model, 'original_language', 'zz', []))->toBe('zz');
});
