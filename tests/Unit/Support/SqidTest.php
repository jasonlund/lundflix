<?php

use App\Support\Sqid;

beforeEach(function () {
    Sqid::reset();
});

it('encodes and decodes a round-trip correctly', function () {
    $encoded = Sqid::encode(42);
    $decoded = Sqid::decode($encoded);

    expect($decoded)->toBe(42);
});

it('produces consistent encoding for the same id', function () {
    $first = Sqid::encode(100);
    $second = Sqid::encode(100);

    expect($first)->toBe($second);
});

it('respects minimum length', function () {
    $minLength = (int) config('sqids.min_length', 8);
    $encoded = Sqid::encode(1);

    expect(strlen($encoded))->toBeGreaterThanOrEqual($minLength);
});

it('returns null when decoding an invalid sqid', function () {
    $decoded = Sqid::decode('!!!invalid!!!');

    expect($decoded)->toBeNull();
});

it('encodes different ids to different strings', function () {
    $a = Sqid::encode(1);
    $b = Sqid::encode(2);

    expect($a)->not->toBe($b);
});
