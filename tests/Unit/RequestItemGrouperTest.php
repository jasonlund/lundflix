<?php

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use App\Services\RequestItemGrouper;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('separates movies from episodes', function () {
    $movie = Movie::factory()->create();
    $show = Show::factory()->create();
    $episode = Episode::factory()->create(['show_id' => $show->id]);

    $grouper = new RequestItemGrouper;
    $result = $grouper->group(collect([$movie, $episode]));

    expect($result['movies'])->toHaveCount(1);
    expect($result['movies']->first()->id)->toBe($movie->id);
    expect($result['shows'])->toHaveCount(1);
});

it('groups episodes by show', function () {
    $show1 = Show::factory()->create(['name' => 'Alpha Show']);
    $show2 = Show::factory()->create(['name' => 'Beta Show']);

    Episode::factory()->create(['show_id' => $show1->id, 'season' => 1]);
    Episode::factory()->create(['show_id' => $show2->id, 'season' => 1]);

    $episodes = Episode::with('show')->get();

    $grouper = new RequestItemGrouper;
    $result = $grouper->group($episodes);

    expect($result['shows'])->toHaveCount(2);
    expect($result['shows'][0]['show']->name)->toBe('Alpha Show');
    expect($result['shows'][1]['show']->name)->toBe('Beta Show');
});

it('groups episodes by season within a show', function () {
    $show = Show::factory()->create();

    Episode::factory()->create(['show_id' => $show->id, 'season' => 1]);
    Episode::factory()->create(['show_id' => $show->id, 'season' => 2]);

    $episodes = Episode::with('show')->get();

    $grouper = new RequestItemGrouper;
    $result = $grouper->group($episodes);

    expect($result['shows'])->toHaveCount(1);
    expect($result['shows'][0]['seasons'])->toHaveCount(2);
    expect($result['shows'][0]['seasons'][0]['season'])->toBe(1);
    expect($result['shows'][0]['seasons'][1]['season'])->toBe(2);
});

it('detects a full season when all episodes are in cart', function () {
    $show = Show::factory()->create();

    // Create 3 episodes for season 1
    Episode::factory()->count(3)->sequence(
        ['number' => 1],
        ['number' => 2],
        ['number' => 3],
    )->create([
        'show_id' => $show->id,
        'season' => 1,
        'type' => 'regular',
    ]);

    // Add all to cart
    $episodes = Episode::with('show')->get();

    $grouper = new RequestItemGrouper;
    $result = $grouper->group($episodes);

    expect($result['shows'][0]['seasons'][0]['is_full'])->toBeTrue();
});

it('detects partial season when not all episodes are in cart', function () {
    $show = Show::factory()->create();

    // Create 3 episodes for season 1
    Episode::factory()->count(3)->sequence(
        ['number' => 1],
        ['number' => 2],
        ['number' => 3],
    )->create([
        'show_id' => $show->id,
        'season' => 1,
        'type' => 'regular',
    ]);

    // Only get first 2 episodes
    $episodes = Episode::with('show')->where('number', '<=', 2)->get();

    $grouper = new RequestItemGrouper;
    $result = $grouper->group($episodes);

    expect($result['shows'][0]['seasons'][0]['is_full'])->toBeFalse();
});

it('finds consecutive runs based on airdate order', function () {
    $show = Show::factory()->create();

    // Create 5 episodes with different airdates
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'type' => 'regular',
        'airdate' => '2024-01-01',
    ]);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 2,
        'type' => 'regular',
        'airdate' => '2024-01-08',
    ]);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 3,
        'type' => 'regular',
        'airdate' => '2024-01-15',
    ]);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 4,
        'type' => 'regular',
        'airdate' => '2024-01-22',
    ]);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 5,
        'type' => 'regular',
        'airdate' => '2024-01-29',
    ]);

    // Cart has episodes 1, 2, 4, 5 (gap at 3)
    $episodes = Episode::with('show')
        ->whereIn('number', [1, 2, 4, 5])
        ->get();

    $grouper = new RequestItemGrouper;
    $result = $grouper->group($episodes);

    $runs = $result['shows'][0]['seasons'][0]['runs'];
    expect($runs)->toHaveCount(2);
    expect($runs[0])->toHaveCount(2); // E01, E02
    expect($runs[1])->toHaveCount(2); // E04, E05
});

it('includes specials in runs when consecutive by airdate', function () {
    $show = Show::factory()->create();

    // E07 airs before E08 which airs before S01
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 7,
        'type' => 'regular',
        'airdate' => '2024-02-01',
    ]);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 8,
        'type' => 'regular',
        'airdate' => '2024-02-08',
    ]);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'type' => 'significant_special',
        'airdate' => '2024-02-15',
    ]);

    $episodes = Episode::with('show')->get();

    $grouper = new RequestItemGrouper;
    $result = $grouper->group($episodes);

    $runs = $result['shows'][0]['seasons'][0]['runs'];
    expect($runs)->toHaveCount(1);
    expect($runs[0])->toHaveCount(3);
    expect($runs[0][0]->number)->toBe(7);
    expect($runs[0][1]->number)->toBe(8);
    expect($runs[0][2]->isSpecial())->toBeTrue();
});

it('handles empty collection', function () {
    $grouper = new RequestItemGrouper;
    $result = $grouper->group(collect());

    expect($result['movies'])->toBeEmpty();
    expect($result['shows'])->toBeEmpty();
});

it('handles movies only', function () {
    $movies = Movie::factory()->count(3)->create();

    $grouper = new RequestItemGrouper;
    $result = $grouper->group($movies);

    expect($result['movies'])->toHaveCount(3);
    expect($result['shows'])->toBeEmpty();
});

it('excludes insignificant specials from full season calculation', function () {
    $show = Show::factory()->create();

    // Create 2 regular episodes and 1 insignificant special
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'type' => 'regular',
    ]);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 2,
        'type' => 'regular',
    ]);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'type' => 'insignificant_special',
    ]);

    // Cart has only the 2 regular episodes
    $episodes = Episode::with('show')
        ->where('type', '!=', 'insignificant_special')
        ->get();

    $grouper = new RequestItemGrouper;
    $result = $grouper->group($episodes);

    // Should be considered full since insignificant specials are excluded
    expect($result['shows'][0]['seasons'][0]['is_full'])->toBeTrue();
});

it('sorts runs by airdate order', function () {
    $show = Show::factory()->create();

    // Create episodes with mixed airdate order
    $ep3 = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 3,
        'type' => 'regular',
        'airdate' => '2024-01-15',
    ]);
    $ep1 = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'type' => 'regular',
        'airdate' => '2024-01-01',
    ]);
    $ep2 = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 2,
        'type' => 'regular',
        'airdate' => '2024-01-08',
    ]);

    $episodes = Episode::with('show')->get();

    $grouper = new RequestItemGrouper;
    $result = $grouper->group($episodes);

    $runs = $result['shows'][0]['seasons'][0]['runs'];
    expect($runs)->toHaveCount(1);
    expect($runs[0][0]->number)->toBe(1);
    expect($runs[0][1]->number)->toBe(2);
    expect($runs[0][2]->number)->toBe(3);
});
