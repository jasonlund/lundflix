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

it('filters to english shows only', function () {
    Show::factory()->create(['name' => 'Breaking Bad', 'language' => 'English']);
    Show::factory()->create(['name' => 'Dark German Show', 'language' => 'German']);

    $results = $this->searchService->search('a', 'shows', 'en');

    expect($results)->toHaveCount(1);
    expect($results->first()->name)->toBe('Breaking Bad');
});

it('filters to english movies only', function () {
    Movie::factory()->withTmdbData()->create(['title' => 'Inception', 'original_language' => 'en']);
    Movie::factory()->withTmdbData()->create(['title' => 'Spirited Away', 'original_language' => 'ja']);

    $results = $this->searchService->search('i', 'movies', 'en');

    expect($results)->toHaveCount(1);
    expect($results->first()->title)->toBe('Inception');
});

it('filters to foreign language shows only', function () {
    Show::factory()->create(['name' => 'Breaking Bad', 'language' => 'English']);
    Show::factory()->create(['name' => 'Dark German Show', 'language' => 'German']);

    $results = $this->searchService->search('a', 'shows', 'foreign');

    expect($results)->toHaveCount(1);
    expect($results->first()->name)->toBe('Dark German Show');
});

it('filters to foreign language movies only', function () {
    Movie::factory()->withTmdbData()->create(['title' => 'Inception', 'original_language' => 'en']);
    Movie::factory()->withTmdbData()->create(['title' => 'Spirited Away', 'original_language' => 'ja']);

    $results = $this->searchService->search('i', 'movies', 'foreign');

    expect($results)->toHaveCount(1);
    expect($results->first()->title)->toBe('Spirited Away');
});

it('returns all languages when language filter is null', function () {
    Show::factory()->create(['name' => 'Breaking Bad', 'language' => 'English']);
    Show::factory()->create(['name' => 'Dark Show', 'language' => 'German']);

    $results = $this->searchService->search('a', 'shows', null);

    expect($results)->toHaveCount(2);
});

it('filters to english when searching both shows and movies', function () {
    Show::factory()->create(['name' => 'The Wire', 'language' => 'English']);
    Show::factory()->create(['name' => 'The Wire Foreign', 'language' => 'German']);
    Movie::factory()->withTmdbData()->create(['title' => 'Wire Movie', 'original_language' => 'en']);
    Movie::factory()->withTmdbData()->create(['title' => 'Wire Film', 'original_language' => 'de']);

    $results = $this->searchService->search('Wire', 'all', 'en');

    expect($results)->toHaveCount(2);
    expect($results->contains(fn ($item) => $item instanceof Show && $item->name === 'The Wire'))->toBeTrue();
    expect($results->contains(fn ($item) => $item instanceof Movie && $item->title === 'Wire Movie'))->toBeTrue();
});

it('filters to foreign when searching both shows and movies', function () {
    Show::factory()->create(['name' => 'The Wire', 'language' => 'English']);
    Show::factory()->create(['name' => 'The Wire Foreign', 'language' => 'German']);
    Movie::factory()->withTmdbData()->create(['title' => 'Wire Movie', 'original_language' => 'en']);
    Movie::factory()->withTmdbData()->create(['title' => 'Wire Film', 'original_language' => 'de']);

    $results = $this->searchService->search('Wire', 'all', 'foreign');

    expect($results)->toHaveCount(2);
    expect($results->contains(fn ($item) => $item instanceof Show && $item->name === 'The Wire Foreign'))->toBeTrue();
    expect($results->contains(fn ($item) => $item instanceof Movie && $item->title === 'Wire Film'))->toBeTrue();
});

it('ignores language filter when searching by imdb id', function () {
    Show::factory()->create(['name' => 'English Show', 'imdb_id' => 'tt9999901', 'language' => 'English']);
    Movie::factory()->withTmdbData()->create(['title' => 'Japanese Movie', 'imdb_id' => 'tt9999901', 'original_language' => 'ja']);

    $results = $this->searchService->search('tt9999901', 'all', 'en');

    expect($results)->toHaveCount(2);
});

it('falls back to database driver when primary driver throws an exception', function () {
    Show::factory()->create(['name' => 'Fallback Test Show', 'language' => 'English']);

    // Simulate a failing primary driver (e.g. Algolia quota exceeded)
    config(['scout.driver' => 'algolia']);

    $results = $this->searchService->search('Fallback Test');

    // Should fall back to database driver and still return results
    expect($results)->toHaveCount(1);
    expect($results->first()->name)->toBe('Fallback Test Show');
    expect(config('scout.driver'))->toBe('database');
});

it('returns results sorted by popularity (num_votes descending)', function () {
    Show::factory()->create(['name' => 'Lost in Space', 'num_votes' => 112340]);
    Show::factory()->create(['name' => 'Lost Girl', 'num_votes' => 34555]);
    Show::factory()->create(['name' => 'Lost', 'num_votes' => 664769]);
    Show::factory()->create(['name' => 'The Lost Room', 'num_votes' => 34698]);

    $results = $this->searchService->search('Lost', 'shows');

    expect($results)->toHaveCount(4);
    expect($results[0]->name)->toBe('Lost');
    expect($results[1]->name)->toBe('Lost in Space');
    expect($results[2]->name)->toBe('The Lost Room');
    expect($results[3]->name)->toBe('Lost Girl');
});

it('returns combined results sorted by popularity across shows and movies', function () {
    Show::factory()->create(['name' => 'Dark', 'num_votes' => 200000, 'language' => 'English']);
    Movie::factory()->withTmdbData()->create(['title' => 'Dark Knight', 'num_votes' => 500000, 'original_language' => 'en']);
    Show::factory()->create(['name' => 'Dark Matter', 'num_votes' => 50000, 'language' => 'English']);

    $results = $this->searchService->search('Dark', 'all', 'en');

    expect($results)->toHaveCount(3);
    expect($results[0]->num_votes)->toBe(500000);
    expect($results[1]->num_votes)->toBe(200000);
    expect($results[2]->num_votes)->toBe(50000);
});

it('does not catch exceptions when already using the database driver', function () {
    // The database driver is already the fallback — exceptions should propagate
    config(['scout.driver' => 'database']);

    // Force an exception by searching with a type that triggers searchBoth,
    // after poisoning the engine manager to throw
    app(\Laravel\Scout\EngineManager::class)->extend('database', function () {
        throw new \RuntimeException('Database driver failed');
    });

    expect(fn () => $this->searchService->search('test'))
        ->toThrow(\RuntimeException::class, 'Database driver failed');
});
