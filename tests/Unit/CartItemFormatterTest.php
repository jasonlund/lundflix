<?php

use App\Models\Episode;
use App\Models\Show;
use App\Support\CartItemFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('formats a single regular episode', function () {
    $show = Show::factory()->create();
    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 5,
        'type' => 'regular',
    ]);

    expect(CartItemFormatter::formatRun([$episode]))->toBe('S01E05');
});

it('formats a single special episode', function () {
    $show = Show::factory()->create();
    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 2,
        'number' => 3,
        'type' => 'significant_special',
    ]);

    expect(CartItemFormatter::formatRun([$episode]))->toBe('S02S03');
});

it('formats a run of consecutive regular episodes', function () {
    $show = Show::factory()->create();
    $episodes = Episode::factory()->count(3)->sequence(
        ['number' => 1],
        ['number' => 2],
        ['number' => 3],
    )->create([
        'show_id' => $show->id,
        'season' => 1,
        'type' => 'regular',
    ]);

    expect(CartItemFormatter::formatRun($episodes))->toBe('S01E01-E03');
});

it('formats a run ending with a special episode', function () {
    $show = Show::factory()->create();
    $episodes = collect([
        Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 1,
            'number' => 7,
            'type' => 'regular',
        ]),
        Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 1,
            'number' => 8,
            'type' => 'regular',
        ]),
        Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 1,
            'number' => 1,
            'type' => 'significant_special',
        ]),
    ]);

    expect(CartItemFormatter::formatRun($episodes))->toBe('S01E07-S01');
});

it('formats a run of consecutive special episodes', function () {
    $show = Show::factory()->create();
    $episodes = Episode::factory()->count(3)->sequence(
        ['number' => 1],
        ['number' => 2],
        ['number' => 3],
    )->create([
        'show_id' => $show->id,
        'season' => 1,
        'type' => 'significant_special',
    ]);

    expect(CartItemFormatter::formatRun($episodes))->toBe('S01S01-S03');
});

it('formats a run starting with a special episode', function () {
    $show = Show::factory()->create();
    $episodes = collect([
        Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 2,
            'number' => 1,
            'type' => 'significant_special',
        ]),
        Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 2,
            'number' => 1,
            'type' => 'regular',
        ]),
        Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 2,
            'number' => 2,
            'type' => 'regular',
        ]),
    ]);

    expect(CartItemFormatter::formatRun($episodes))->toBe('S02S01-E02');
});

it('returns empty string for empty input', function () {
    expect(CartItemFormatter::formatRun([]))->toBe('');
});

it('accepts a Collection as input', function () {
    $show = Show::factory()->create();
    $episodes = Episode::factory()->count(2)->sequence(
        ['number' => 4],
        ['number' => 5],
    )->create([
        'show_id' => $show->id,
        'season' => 3,
        'type' => 'regular',
    ]);

    expect(CartItemFormatter::formatRun($episodes))->toBe('S03E04-E05');
});

it('formats a season label', function () {
    expect(CartItemFormatter::formatSeason(1))->toBe('Season 1');
    expect(CartItemFormatter::formatSeason(12))->toBe('Season 12');
});
