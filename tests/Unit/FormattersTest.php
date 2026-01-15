<?php

use App\Support\Formatters;

it('formats runtime in hours and minutes', function () {
    expect(Formatters::runtime(150))->toBe('2h 30m');
});

it('formats runtime with only minutes', function () {
    expect(Formatters::runtime(45))->toBe('45m');
});

it('formats runtime with exactly one hour', function () {
    expect(Formatters::runtime(60))->toBe('1h');
});

it('formats runtime with one hour and one minute', function () {
    expect(Formatters::runtime(61))->toBe('1h 1m');
});

it('returns null for null runtime', function () {
    expect(Formatters::runtime(null))->toBeNull();
});

it('returns null for zero runtime', function () {
    expect(Formatters::runtime(0))->toBeNull();
});

it('returns null for negative runtime', function () {
    expect(Formatters::runtime(-10))->toBeNull();
});
