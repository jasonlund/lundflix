<?php

use App\Jobs\StoreFanart;
use App\Models\Movie;
use App\Models\Show;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Http::preventStrayRequests();
    Queue::fake([StoreFanart::class]);
});

it('dispatches jobs for movies with updated artwork', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    Http::fake([
        'webservice.fanart.tv/v3/movies/latest*' => Http::response([
            ['imdb_id' => 'tt0111161'],
        ]),
        'webservice.fanart.tv/v3/tv/latest*' => Http::response([]),
        'webservice.fanart.tv/v3/movies/tt0111161' => Http::response([
            'name' => 'The Shawshank Redemption',
            'imdb_id' => 'tt0111161',
            'hdmovielogo' => [
                ['id' => '12345', 'url' => 'https://assets.fanart.tv/logo.png', 'lang' => 'en', 'likes' => '5'],
            ],
        ]),
    ]);

    $this->artisan('fanart:sync-updates')
        ->assertSuccessful();

    Queue::assertPushed(StoreFanart::class, fn ($job) => $job->model->is($movie));
});

it('dispatches jobs for shows with updated artwork', function () {
    $show = Show::factory()->create(['thetvdb_id' => 264492]);

    Http::fake([
        'webservice.fanart.tv/v3/movies/latest*' => Http::response([]),
        'webservice.fanart.tv/v3/tv/latest*' => Http::response([
            ['thetvdb_id' => '264492'],
        ]),
        'webservice.fanart.tv/v3/tv/264492' => Http::response([
            'name' => 'Under the Dome',
            'thetvdb_id' => '264492',
            'tvposter' => [
                ['id' => '11111', 'url' => 'https://assets.fanart.tv/poster.jpg', 'lang' => 'en', 'likes' => '3'],
            ],
        ]),
    ]);

    $this->artisan('fanart:sync-updates')
        ->assertSuccessful();

    Queue::assertPushed(StoreFanart::class, fn ($job) => $job->model->is($show));
});

it('ignores updates for media not in local database', function () {
    Http::fake([
        'webservice.fanart.tv/v3/movies/latest*' => Http::response([
            ['imdb_id' => 'tt9999999'],
        ]),
        'webservice.fanart.tv/v3/tv/latest*' => Http::response([]),
    ]);

    $this->artisan('fanart:sync-updates')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('stores last sync timestamp in cache', function () {
    Http::fake([
        'webservice.fanart.tv/v3/movies/latest*' => Http::response([]),
        'webservice.fanart.tv/v3/tv/latest*' => Http::response([]),
    ]);

    $this->artisan('fanart:sync-updates')
        ->assertSuccessful();

    expect(Cache::get('fanart:last_sync_timestamp'))
        ->toBeInt()
        ->toBeGreaterThan(0);
});

it('uses cached timestamp for subsequent syncs', function () {
    $timestamp = now()->subDay()->timestamp;
    Cache::forever('fanart:last_sync_timestamp', $timestamp);

    Http::fake([
        'webservice.fanart.tv/v3/movies/latest*' => Http::response([]),
        'webservice.fanart.tv/v3/tv/latest*' => Http::response([]),
    ]);

    $this->artisan('fanart:sync-updates')
        ->assertSuccessful();

    Http::assertSent(fn ($request) => str_contains($request->url(), "date={$timestamp}"));
});

it('ignores cached timestamp with --fresh option', function () {
    $timestamp = now()->subDay()->timestamp;
    Cache::forever('fanart:last_sync_timestamp', $timestamp);

    Http::fake([
        'webservice.fanart.tv/v3/movies/latest*' => Http::response([]),
        'webservice.fanart.tv/v3/tv/latest*' => Http::response([]),
    ]);

    $this->artisan('fanart:sync-updates', ['--fresh' => true])
        ->assertSuccessful();

    Http::assertSent(fn ($request) => ! str_contains($request->url(), 'date='));
});

it('skips processing when artwork fetch returns null', function () {
    $movie = Movie::factory()->create(['imdb_id' => 'tt0111161']);

    Http::fake([
        'webservice.fanart.tv/v3/movies/latest*' => Http::response([
            ['imdb_id' => 'tt0111161'],
        ]),
        'webservice.fanart.tv/v3/tv/latest*' => Http::response([]),
        'webservice.fanart.tv/v3/movies/tt0111161' => Http::response([], 404),
    ]);

    $this->artisan('fanart:sync-updates')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('continues processing when individual artwork fetch fails', function () {
    $movie1 = Movie::factory()->create(['imdb_id' => 'tt0111161']);
    $movie2 = Movie::factory()->create(['imdb_id' => 'tt0068646']);

    Http::fake([
        'webservice.fanart.tv/v3/movies/latest*' => Http::response([
            ['imdb_id' => 'tt0111161'],
            ['imdb_id' => 'tt0068646'],
        ]),
        'webservice.fanart.tv/v3/tv/latest*' => Http::response([]),
        'webservice.fanart.tv/v3/movies/tt0111161' => Http::response([], 500),
        'webservice.fanart.tv/v3/movies/tt0068646' => Http::response([
            'name' => 'The Godfather',
            'imdb_id' => 'tt0068646',
            'hdmovielogo' => [
                ['id' => '67890', 'url' => 'https://assets.fanart.tv/logo.png', 'lang' => 'en', 'likes' => '10'],
            ],
        ]),
    ]);

    $this->artisan('fanart:sync-updates')
        ->assertSuccessful()
        ->expectsOutputToContain('Failed to fetch artwork for movie tt0111161');

    Queue::assertPushed(StoreFanart::class, fn ($job) => $job->model->is($movie2));
    Queue::assertNotPushed(StoreFanart::class, fn ($job) => $job->model->is($movie1));
});
