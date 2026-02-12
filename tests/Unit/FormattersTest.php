<?php

use App\Models\Movie;
use App\Models\Show;
use App\Support\Formatters;

it('formats runtime in hours and minutes', function () {
    expect(Formatters::runtime(150))->toBe('2h30m');
});

it('formats runtime with only minutes', function () {
    expect(Formatters::runtime(45))->toBe('45m');
});

it('formats runtime with exactly one hour', function () {
    expect(Formatters::runtime(60))->toBe('1h');
});

it('formats runtime with one hour and one minute', function () {
    expect(Formatters::runtime(61))->toBe('1h1m');
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

it('formats year label for a movie with year', function () {
    $movie = Movie::factory()->make(['year' => 1999]);

    expect(Formatters::yearLabel($movie))->toBe('1999');
});

it('returns null year label for a movie without year', function () {
    $movie = Movie::factory()->make(['year' => null]);

    expect(Formatters::yearLabel($movie))->toBeNull();
});

it('formats year label for an ended show', function () {
    $show = Show::factory()->make([
        'premiered' => '2008-01-20',
        'ended' => '2013-09-29',
        'status' => 'Ended',
    ]);

    expect(Formatters::yearLabel($show))->toBe('2008-2013');
});

it('formats year label for a running show', function () {
    $show = Show::factory()->make([
        'premiered' => '2020-06-15',
        'ended' => null,
        'status' => 'Running',
    ]);

    expect(Formatters::yearLabel($show))->toBe('2020-present');
});

it('formats year label for a show with only premiere date', function () {
    $show = Show::factory()->make([
        'premiered' => '2022-03-10',
        'ended' => null,
        'status' => 'To Be Determined',
    ]);

    expect(Formatters::yearLabel($show))->toBe('2022');
});

it('returns null year label for a show without premiere date', function () {
    $show = Show::factory()->make([
        'premiered' => null,
        'ended' => null,
        'status' => 'In Development',
    ]);

    expect(Formatters::yearLabel($show))->toBeNull();
});

it('formats compact year label for a running show without present', function () {
    $show = Show::factory()->make([
        'premiered' => '2020-06-15',
        'ended' => null,
        'status' => 'Running',
    ]);

    expect(Formatters::compactYearLabel($show))->toBe("'20-");
});

it('formats compact year label for an ended show', function () {
    $show = Show::factory()->make([
        'premiered' => '2008-01-20',
        'ended' => '2013-09-29',
        'status' => 'Ended',
    ]);

    expect(Formatters::compactYearLabel($show))->toBe("'08-'13");
});

it('formats compact year label for a movie with year', function () {
    $movie = Movie::factory()->make(['year' => 1999]);

    expect(Formatters::compactYearLabel($movie))->toBe("'99");
});

it('returns null compact year label for a movie without year', function () {
    $movie = Movie::factory()->make(['year' => null]);

    expect(Formatters::compactYearLabel($movie))->toBeNull();
});

it('returns null compact year label for a show without premiere date', function () {
    $show = Show::factory()->make([
        'premiered' => null,
        'ended' => null,
        'status' => 'In Development',
    ]);

    expect(Formatters::compactYearLabel($show))->toBeNull();
});

it('formats compact year label for a show with only premiere date', function () {
    $show = Show::factory()->make([
        'premiered' => '2022-03-10',
        'ended' => null,
        'status' => 'To Be Determined',
    ]);

    expect(Formatters::compactYearLabel($show))->toBe("'22");
});

it('formats approximate runtime with tilde prefix', function () {
    expect(Formatters::runtime(49, approximate: true))->toBe('~49m');
});

it('formats approximate runtime in hours and minutes', function () {
    expect(Formatters::runtime(150, approximate: true))->toBe('~2h30m');
});

it('formats runtime for a movie', function () {
    $movie = Movie::factory()->make(['runtime' => 136]);

    expect(Formatters::runtimeFor($movie))->toBe('2h16m');
});

it('formats exact runtime for a show', function () {
    $show = Show::factory()->make(['runtime' => 60, 'average_runtime' => 60]);

    expect(Formatters::runtimeFor($show))->toBe('1h');
});

it('formats approximate runtime for a show with only average_runtime', function () {
    $show = Show::factory()->make(['runtime' => null, 'average_runtime' => 49]);

    expect(Formatters::runtimeFor($show))->toBe('~49m');
});

it('returns null runtime for a show with no runtime', function () {
    $show = Show::factory()->make(['runtime' => null, 'average_runtime' => null]);

    expect(Formatters::runtimeFor($show))->toBeNull();
});
