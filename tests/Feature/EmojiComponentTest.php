<?php

use App\Support\Emoji;
use Illuminate\Support\Facades\Blade;

it('renders an img tag for a known emoji character', function () {
    $html = Blade::render('<x-emoji char="🤠" />');

    expect($html)
        ->toContain('<img')
        ->toContain('src="'.Emoji::assetUrl('1f920').'"')
        ->toContain('alt="🤠"')
        ->toContain('loading="lazy"')
        ->toContain('decoding="async"');
});

it('renders an img tag for a known country flag', function () {
    $html = Blade::render('<x-emoji country="US" />');

    expect($html)
        ->toContain('<img')
        ->toContain('src="'.Emoji::assetUrl('1f1fa-1f1f8').'"')
        ->toContain('alt="US"');
});

it('normalises lowercase country codes', function () {
    $html = Blade::render('<x-emoji country="jp" />');

    expect($html)
        ->toContain('src="'.Emoji::assetUrl('1f1ef-1f1f5').'"')
        ->toContain('alt="JP"');
});

it('falls back to a span with the unicode emoji when the asset is missing', function () {
    // ZZ is not a valid ISO 3166-1 alpha-2 code; no flag PNG is committed.
    $html = Blade::render('<x-emoji country="ZZ" />');

    expect($html)
        ->not->toContain('<img')
        ->toContain('aria-label="ZZ"')
        ->toContain('🇿🇿');
});
