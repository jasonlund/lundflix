<?php

use App\Enums\EpisodeType;
use App\Models\Episode;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('has a code accessor for regular episodes', function () {
    $episode = Episode::factory()->create([
        'season' => 2,
        'number' => 5,
        'type' => EpisodeType::Regular,
    ]);

    expect($episode->code)->toBe('s02e05');
});

it('has a code accessor for special episodes', function () {
    $episode = Episode::factory()->special()->create([
        'season' => 24,
        'number' => 1,
    ]);

    expect($episode->code)->toBe('s24s01');
});

it('formats single digit season and episode with zero padding', function () {
    $episode = Episode::factory()->create([
        'season' => 1,
        'number' => 1,
        'type' => EpisodeType::Regular,
    ]);

    expect($episode->code)->toBe('s01e01');
});

it('identifies regular episodes as not special', function () {
    $episode = Episode::factory()->create([
        'type' => EpisodeType::Regular,
    ]);

    expect($episode->isSpecial())->toBeFalse();
});

it('identifies significant specials as special', function () {
    $episode = Episode::factory()->special()->create();

    expect($episode->isSpecial())->toBeTrue();
});
