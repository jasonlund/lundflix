<?php

use App\Models\Show;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

function fakeTmdbShowFind(string $externalId, ?int $tmdbId, string $source = 'imdb_id'): array
{
    $key = "api.themoviedb.org/3/find/{$externalId}*";

    if ($tmdbId === null) {
        return [$key => Http::response(['tv_results' => []])];
    }

    return [$key => Http::response([
        'tv_results' => [['id' => $tmdbId, 'name' => 'Test Show']],
    ])];
}

function fakeTmdbShowDetails(int $tmdbId, array $overrides = []): array
{
    $defaults = [
        'id' => $tmdbId,
        'overview' => 'A great show.',
        'tagline' => 'The best show ever.',
        'original_name' => 'Test Show',
        'original_language' => 'en',
        'spoken_languages' => [['iso_639_1' => 'en', 'english_name' => 'English']],
        'production_companies' => [['id' => 1, 'name' => 'Test Studio']],
        'origin_country' => ['US'],
        'content_ratings' => ['results' => [['iso_3166_1' => 'US', 'rating' => 'TV-MA']]],
        'alternative_titles' => ['results' => []],
        'homepage' => 'https://example.com',
        'in_production' => true,
        'external_ids' => ['tvdb_id' => 999, 'imdb_id' => 'tt1234567'],
        'images' => [
            'posters' => [
                ['file_path' => '/poster.jpg', 'iso_639_1' => 'en', 'vote_average' => 5.0, 'vote_count' => 10, 'width' => 500, 'height' => 750],
            ],
            'backdrops' => [],
            'logos' => [],
        ],
    ];

    return ["api.themoviedb.org/3/tv/{$tmdbId}*" => Http::response(array_merge($defaults, $overrides))];
}

function fakeTmdbShowChanges(array $tmdbIds = []): array
{
    return ['api.themoviedb.org/3/tv/changes*' => Http::response([
        'results' => array_map(fn (int $id) => ['id' => $id, 'adult' => false], $tmdbIds),
        'page' => 1,
        'total_pages' => 1,
        'total_results' => count($tmdbIds),
    ])];
}

it('syncs tmdb data for unsynced shows', function () {
    $show = Show::factory()->create(['tmdb_id' => null, 'tmdb_synced_at' => null]);

    Http::fake([
        ...fakeTmdbShowFind($show->imdb_id, 1396),
        ...fakeTmdbShowDetails(1396),
        ...fakeTmdbShowChanges(),
    ]);

    $this->artisan('tmdb:sync-shows')->assertSuccessful();

    $show->refresh();

    expect($show->tmdb_id)->toBe(1396)
        ->and($show->overview)->toBe('A great show.')
        ->and($show->tmdb_synced_at)->not->toBeNull()
        ->and($show->media()->count())->toBe(1);
});

it('falls back to thetvdb_id when imdb_id not found', function () {
    $show = Show::factory()->create([
        'imdb_id' => null,
        'thetvdb_id' => 81189,
        'tmdb_id' => null,
        'tmdb_synced_at' => null,
    ]);

    Http::fake([
        'api.themoviedb.org/3/find/81189*' => Http::response([
            'tv_results' => [['id' => 1396, 'name' => 'Test Show']],
        ]),
        ...fakeTmdbShowDetails(1396),
        ...fakeTmdbShowChanges(),
    ]);

    $this->artisan('tmdb:sync-shows')->assertSuccessful();

    $show->refresh();

    expect($show->tmdb_id)->toBe(1396);
});

it('updates recently changed shows', function () {
    $show = Show::factory()->withTmdbData()->create([
        'tmdb_id' => 1396,
        'overview' => 'Old overview',
    ]);

    Http::fake([
        ...fakeTmdbShowChanges([1396]),
        ...fakeTmdbShowDetails(1396, ['overview' => 'Updated overview']),
    ]);

    $this->artisan('tmdb:sync-shows')->assertSuccessful();

    $show->refresh();

    expect($show->overview)->toBe('Updated overview');
});

it('reports when all shows are up to date', function () {
    Show::factory()->withTmdbData()->create();

    Http::fake([
        ...fakeTmdbShowChanges(),
    ]);

    $this->artisan('tmdb:sync-shows')
        ->expectsOutputToContain('All shows are up to date with TMDB.')
        ->assertSuccessful();
});

it('respects --limit option', function () {
    Show::factory()->count(5)->sequence(
        ['imdb_id' => 'tt0000001', 'tmdb_id' => null, 'tmdb_synced_at' => null],
        ['imdb_id' => 'tt0000002', 'tmdb_id' => null, 'tmdb_synced_at' => null],
        ['imdb_id' => 'tt0000003', 'tmdb_id' => null, 'tmdb_synced_at' => null],
        ['imdb_id' => 'tt0000004', 'tmdb_id' => null, 'tmdb_synced_at' => null],
        ['imdb_id' => 'tt0000005', 'tmdb_id' => null, 'tmdb_synced_at' => null],
    )->create();

    Http::fake([
        'api.themoviedb.org/3/find/*' => Http::response(['tv_results' => []]),
    ]);

    $this->artisan('tmdb:sync-shows', ['--limit' => 2])->assertSuccessful();

    expect(Show::whereNotNull('tmdb_synced_at')->count())->toBe(2);
});

it('processes all shows with --fresh flag', function () {
    $show = Show::factory()->withTmdbData()->create();

    Http::fake([
        "api.themoviedb.org/3/find/{$show->imdb_id}*" => Http::response([
            'tv_results' => [['id' => $show->tmdb_id, 'name' => 'Test']],
        ]),
        "api.themoviedb.org/3/tv/{$show->tmdb_id}*" => Http::response([
            'id' => $show->tmdb_id,
            'overview' => 'Fresh sync',
            'tagline' => '',
            'original_name' => 'Test',
            'original_language' => 'en',
            'content_ratings' => ['results' => []],
            'alternative_titles' => ['results' => []],
            'external_ids' => ['tvdb_id' => $show->thetvdb_id],
            'images' => ['posters' => [], 'backdrops' => [], 'logos' => []],
        ]),
    ]);

    $this->artisan('tmdb:sync-shows', ['--fresh' => true])->assertSuccessful();

    $show->refresh();

    expect($show->overview)->toBe('Fresh sync');
});

it('preserves existing thetvdb_id when tmdb lacks tvdb external id', function () {
    $show = Show::factory()->create([
        'thetvdb_id' => 81189,
        'tmdb_id' => null,
        'tmdb_synced_at' => null,
    ]);

    Http::fake([
        ...fakeTmdbShowFind($show->imdb_id, 1396),
        ...fakeTmdbShowDetails(1396, ['external_ids' => ['tvdb_id' => null, 'imdb_id' => $show->imdb_id]]),
        ...fakeTmdbShowChanges(),
    ]);

    $this->artisan('tmdb:sync-shows')->assertSuccessful();

    $show->refresh();
    expect($show->thetvdb_id)->toBe(81189);
});

it('marks show as synced when not found on tmdb', function () {
    $show = Show::factory()->create(['tmdb_id' => null, 'tmdb_synced_at' => null]);

    Http::fake([
        "api.themoviedb.org/3/find/{$show->imdb_id}*" => Http::response(['tv_results' => []]),
        ...fakeTmdbShowChanges(),
    ]);

    $this->artisan('tmdb:sync-shows')->assertSuccessful();

    $show->refresh();

    expect($show->tmdb_synced_at)->not->toBeNull()
        ->and($show->tmdb_id)->toBeNull();
});
