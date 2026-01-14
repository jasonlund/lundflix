<?php

use App\Models\Movie;
use App\Models\Show;
use App\Services\SearchService;

beforeEach(function () {
    // Use collection driver for tests (SQLite doesn't support full-text search)
    config(['scout.driver' => 'collection']);

    $this->searchService = new SearchService;
});

it('searches shows by name', function () {
    Show::factory()->create(['name' => 'Breaking Bad', 'imdb_id' => 'tt0903747']);
    Show::factory()->create(['name' => 'Better Call Saul', 'imdb_id' => 'tt3032476']);

    $results = $this->searchService->search('Breaking', 'shows');

    expect($results)->toHaveCount(1);
    expect($results->first()->name)->toBe('Breaking Bad');
});

it('searches movies by title', function () {
    Movie::factory()->create(['title' => 'Inception', 'imdb_id' => 'tt1375666']);
    Movie::factory()->create(['title' => 'Interstellar', 'imdb_id' => 'tt0816692']);

    $results = $this->searchService->search('Inception', 'movies');

    expect($results)->toHaveCount(1);
    expect($results->first()->title)->toBe('Inception');
});

it('searches both shows and movies when type is all', function () {
    Show::factory()->create(['name' => 'Unique Show Title XYZ', 'imdb_id' => 'tt5753856']);
    Movie::factory()->create(['title' => 'Unique Movie Title ABC', 'imdb_id' => 'tt0468569']);

    $showResults = $this->searchService->search('Unique Show Title XYZ', 'all');
    $movieResults = $this->searchService->search('Unique Movie Title ABC', 'all');

    expect($showResults)->toHaveCount(1);
    expect($movieResults)->toHaveCount(1);
});

it('finds show by imdb id', function () {
    Show::factory()->create(['name' => 'Breaking Bad', 'imdb_id' => 'tt0903747']);

    $results = $this->searchService->search('tt0903747');

    expect($results)->toHaveCount(1);
    expect($results->first())->toBeInstanceOf(Show::class);
    expect($results->first()->name)->toBe('Breaking Bad');
});

it('finds movie by imdb id', function () {
    Movie::factory()->create(['title' => 'Inception', 'imdb_id' => 'tt1375666']);

    $results = $this->searchService->search('tt1375666');

    expect($results)->toHaveCount(1);
    expect($results->first())->toBeInstanceOf(Movie::class);
    expect($results->first()->title)->toBe('Inception');
});

it('filters by type shows only', function () {
    Show::factory()->create(['name' => 'Test Show', 'imdb_id' => 'tt1111111']);
    Movie::factory()->create(['title' => 'Test Movie', 'imdb_id' => 'tt2222222']);

    $results = $this->searchService->search('Test', 'shows');

    expect($results)->toHaveCount(1);
    expect($results->first())->toBeInstanceOf(Show::class);
});

it('filters by type movies only', function () {
    Show::factory()->create(['name' => 'Test Show', 'imdb_id' => 'tt1111111']);
    Movie::factory()->create(['title' => 'Test Movie', 'imdb_id' => 'tt2222222']);

    $results = $this->searchService->search('Test', 'movies');

    expect($results)->toHaveCount(1);
    expect($results->first())->toBeInstanceOf(Movie::class);
});

it('returns empty collection for empty query', function () {
    $results = $this->searchService->search('');

    expect($results)->toBeEmpty();
});

it('returns empty collection for whitespace query', function () {
    $results = $this->searchService->search('   ');

    expect($results)->toBeEmpty();
});
