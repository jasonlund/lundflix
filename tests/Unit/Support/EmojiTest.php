<?php

use App\Support\Emoji;

it('converts a single emoji char to its hex codepoint', function () {
    expect(Emoji::codepoints('🤠'))->toBe('1f920');
});

it('converts a regional indicator pair to a hyphenated codepoint stem', function () {
    expect(Emoji::codepoints('🇺🇸'))->toBe('1f1fa-1f1f8');
});

it('strips the U+FE0F variation selector', function () {
    expect(Emoji::codepoints("\u{2764}\u{FE0F}"))->toBe('2764');
});

it('preserves ZWJ sequences', function () {
    expect(Emoji::codepoints("\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}"))
        ->toBe('1f468-200d-1f469-200d-1f467');
});

it('converts ISO 3166-1 alpha-2 codes to regional indicator codepoints', function (string $iso, string $expected) {
    expect(Emoji::flagCodepoints($iso))->toBe($expected);
})->with([
    'US' => ['US', '1f1fa-1f1f8'],
    'GB' => ['GB', '1f1ec-1f1e7'],
    'JP' => ['JP', '1f1ef-1f1f5'],
    'lowercase br' => ['br', '1f1e7-1f1f7'],
]);

it('returns a Vite asset URL when the PNG exists', function () {
    expect(Emoji::assetUrl('1f920'))->not->toBeNull();
});

it('returns null when the PNG is missing', function () {
    expect(Emoji::assetUrl('deadbeef'))->toBeNull();
});
