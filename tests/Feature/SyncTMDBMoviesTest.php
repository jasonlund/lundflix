<?php

use App\Jobs\StoreTMDBData;
use App\Models\Movie;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake([StoreTMDBData::class]);
});

it('dispatches jobs for movies without tmdb data', function () {
    $movie = Movie::factory()->create();

    $this->artisan('tmdb:sync-movies')
        ->assertSuccessful();

    Queue::assertPushed(StoreTMDBData::class, fn ($job) => $job->movie->is($movie));
});

it('skips movies that already have tmdb data', function () {
    Movie::factory()->withTmdbData()->create();

    $this->artisan('tmdb:sync-movies')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('processes all movies with --fresh flag', function () {
    $movie = Movie::factory()->withTmdbData()->create();

    $this->artisan('tmdb:sync-movies', ['--fresh' => true])
        ->assertSuccessful();

    Queue::assertPushed(StoreTMDBData::class, fn ($job) => $job->movie->is($movie));
});

it('respects --limit option', function () {
    Movie::factory()->count(5)->create();

    $this->artisan('tmdb:sync-movies', ['--limit' => 2])
        ->assertSuccessful();

    Queue::assertPushed(StoreTMDBData::class, 2);
});

it('reports when all movies are synced', function () {
    Movie::factory()->withTmdbData()->create();

    $this->artisan('tmdb:sync-movies')
        ->expectsOutputToContain('All movies are already synced with TMDB.')
        ->assertSuccessful();
});

it('dispatches jobs for multiple movies', function () {
    $movie1 = Movie::factory()->create(['imdb_id' => 'tt0111161']);
    $movie2 = Movie::factory()->create(['imdb_id' => 'tt0068646']);

    $this->artisan('tmdb:sync-movies')
        ->assertSuccessful();

    Queue::assertPushed(StoreTMDBData::class, fn ($job) => $job->movie->is($movie1));
    Queue::assertPushed(StoreTMDBData::class, fn ($job) => $job->movie->is($movie2));
});
