<?php

use App\Services\TVMazeService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('fetches shows from tvmaze api', function () {
    Http::fake([
        'api.tvmaze.com/shows*' => Http::response([
            ['id' => 1, 'name' => 'Under the Dome'],
            ['id' => 2, 'name' => 'Person of Interest'],
        ]),
    ]);

    $service = new TVMazeService;
    $shows = $service->shows(0);

    expect($shows)->toHaveCount(2)
        ->and($shows->first()['name'])->toBe('Under the Dome');
});

it('passes page parameter to api', function () {
    Http::fake([
        'api.tvmaze.com/shows?page=5' => Http::response([
            ['id' => 1250, 'name' => 'Some Show'],
        ]),
    ]);

    $service = new TVMazeService;
    $shows = $service->shows(5);

    expect($shows)->toHaveCount(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'page=5'));
});

it('throws exception when no more pages exist', function () {
    Http::fake([
        'api.tvmaze.com/shows*' => Http::response([], 404),
    ]);

    $service = new TVMazeService;
    $service->shows(999);
})->throws(\Illuminate\Http\Client\RequestException::class);

it('fetches episodes for a show including specials', function () {
    Http::fake([
        'api.tvmaze.com/shows/1/episodes?specials=1' => Http::response([
            ['id' => 1, 'name' => 'Pilot', 'season' => 1, 'number' => 1],
            ['id' => 2, 'name' => 'The Fire', 'season' => 1, 'number' => 2],
        ]),
    ]);

    $service = new TVMazeService;
    $episodes = $service->episodes(1);

    expect($episodes)->toHaveCount(2)
        ->and($episodes[0]['name'])->toBe('Pilot');
});

it('throws exception when show does not exist for episodes', function () {
    Http::fake([
        'api.tvmaze.com/shows/999999/episodes?specials=1' => Http::response([], 404),
    ]);

    $service = new TVMazeService;
    $service->episodes(999999);
})->throws(\Illuminate\Http\Client\RequestException::class);

it('retries when rate limited and succeeds', function () {
    Http::fakeSequence('api.tvmaze.com/shows/1')
        ->push(null, 429)
        ->push(['id' => 1, 'name' => 'Test Show']);

    $service = new TVMazeService;
    $show = $service->show(1);

    expect($show)->not->toBeNull()
        ->and($show['name'])->toBe('Test Show');
    Http::assertSentCount(2);
});

it('throws exception after exhausting retries on rate limit', function () {
    Http::fakeSequence('api.tvmaze.com/shows/1')
        ->push(null, 429)
        ->push(null, 429)
        ->push(null, 429);

    $service = new TVMazeService;
    $service->show(1);
})->throws(\Illuminate\Http\Client\RequestException::class);
