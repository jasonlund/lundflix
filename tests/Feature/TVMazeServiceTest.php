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

it('returns null when no more pages exist', function () {
    Http::fake([
        'api.tvmaze.com/shows*' => Http::response([], 404),
    ]);

    $service = new TVMazeService;
    $shows = $service->shows(999);

    expect($shows)->toBeNull();
});
