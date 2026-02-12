<?php

use App\Actions\Tv\UpsertEpisodes;
use App\Models\Episode;
use App\Models\Show;

it('stores episodes in the database', function () {
    $show = Show::factory()->create();

    $episodes = [
        ['id' => 1, 'name' => 'Pilot', 'season' => 1, 'number' => 1, 'airdate' => '2013-06-24', 'runtime' => 60],
        ['id' => 2, 'name' => 'The Fire', 'season' => 1, 'number' => 2, 'airdate' => '2013-07-01', 'runtime' => 60],
    ];

    app(UpsertEpisodes::class)->fromApi($show, $episodes);

    expect(Episode::count())->toBe(2)
        ->and(Episode::where('tvmaze_id', 1)->first()->name)->toBe('Pilot')
        ->and(Episode::where('tvmaze_id', 2)->first()->name)->toBe('The Fire');
});

it('updates existing episodes on duplicate tvmaze_id', function () {
    $show = Show::factory()->create();

    // First insert
    app(UpsertEpisodes::class)->fromApi($show, [
        ['id' => 1, 'name' => 'Original Name', 'season' => 1, 'number' => 1, 'airdate' => null, 'runtime' => null],
    ]);

    expect(Episode::count())->toBe(1);

    // Update with new name
    app(UpsertEpisodes::class)->fromApi($show, [
        ['id' => 1, 'name' => 'Updated Name', 'season' => 1, 'number' => 1, 'airdate' => null, 'runtime' => null],
    ]);

    expect(Episode::count())->toBe(1)
        ->and(Episode::where('tvmaze_id', 1)->first()->name)->toBe('Updated Name');
});

it('stores all episode fields correctly', function () {
    $show = Show::factory()->create();

    $episodes = [
        [
            'id' => 100,
            'name' => 'Test Episode',
            'season' => 2,
            'number' => 5,
            'type' => 'regular',
            'airdate' => '2023-05-15',
            'airtime' => '21:00',
            'runtime' => 45,
            'rating' => ['average' => 8.5],
            'image' => ['medium' => 'http://example.com/medium.jpg'],
            'summary' => '<p>Test summary</p>',
        ],
    ];

    app(UpsertEpisodes::class)->fromApi($show, $episodes);

    $episode = Episode::where('tvmaze_id', 100)->first();

    expect($episode->show_id)->toBe($show->id)
        ->and($episode->season)->toBe(2)
        ->and($episode->number)->toBe(5)
        ->and($episode->type)->toBe('regular')
        ->and($episode->runtime)->toBe(45)
        ->and($episode->rating)->toBe(['average' => 8.5])
        ->and($episode->summary)->toBe('<p>Test summary</p>');
});
