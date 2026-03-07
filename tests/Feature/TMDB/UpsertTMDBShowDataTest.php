<?php

use App\Actions\TMDB\UpsertTMDBShowData;
use App\Models\Show;

it('maps tmdb api response to database columns', function () {
    $details = [
        'id' => 1396,
        'overview' => 'A chemistry teacher diagnosed with cancer.',
        'tagline' => 'All Hail the King',
        'original_name' => 'Breaking Bad',
        'original_language' => 'en',
        'spoken_languages' => [
            ['iso_639_1' => 'en', 'english_name' => 'English', 'name' => 'English'],
        ],
        'production_companies' => [
            ['id' => 2605, 'name' => 'Sony Pictures Television'],
        ],
        'origin_country' => ['US'],
        'content_ratings' => [
            'results' => [
                ['iso_3166_1' => 'US', 'rating' => 'TV-MA'],
                ['iso_3166_1' => 'GB', 'rating' => '18'],
            ],
        ],
        'alternative_titles' => [
            'results' => [
                ['iso_3166_1' => 'DE', 'title' => 'Breaking Bad - Die Serie', 'type' => ''],
            ],
        ],
        'homepage' => 'https://www.amc.com/shows/breaking-bad',
        'in_production' => false,
        'external_ids' => [
            'tvdb_id' => 81189,
            'imdb_id' => 'tt0903747',
        ],
    ];

    $mapped = UpsertTMDBShowData::mapFromApi($details);

    expect($mapped['tmdb_id'])->toBe(1396)
        ->and($mapped['overview'])->toBe('A chemistry teacher diagnosed with cancer.')
        ->and($mapped['tagline'])->toBe('All Hail the King')
        ->and($mapped['original_name'])->toBe('Breaking Bad')
        ->and($mapped['original_language'])->toBe('en')
        ->and($mapped['spoken_languages'])->toHaveCount(1)
        ->and($mapped['production_companies'])->toHaveCount(1)
        ->and($mapped['origin_country'])->toBe(['US'])
        ->and($mapped['content_ratings'])->toHaveCount(2)
        ->and($mapped['content_ratings'][0]['rating'])->toBe('TV-MA')
        ->and($mapped['alternative_titles'])->toHaveCount(1)
        ->and($mapped['homepage'])->toBe('https://www.amc.com/shows/breaking-bad')
        ->and($mapped['in_production'])->toBeFalse()
        ->and($mapped)->not->toHaveKey('thetvdb_id');
});

it('stores null for empty overview and tagline', function () {
    $mapped = UpsertTMDBShowData::mapFromApi([
        'id' => 1,
        'overview' => '',
        'tagline' => '',
        'content_ratings' => ['results' => []],
        'alternative_titles' => ['results' => []],
    ]);

    expect($mapped['overview'])->toBeNull()
        ->and($mapped['tagline'])->toBeNull();
});

it('upserts show data keyed by tvmaze_id', function () {
    $show = Show::factory()->create(['tmdb_id' => null, 'tmdb_synced_at' => null]);

    $upsert = app(UpsertTMDBShowData::class);
    $upsert->upsert([
        [
            'tvmaze_id' => $show->tvmaze_id,
            'name' => $show->name,
            'tmdb_id' => 1396,
            'tmdb_synced_at' => now()->toDateTimeString(),
            'overview' => 'Test overview',
            'tagline' => null,
            'original_name' => null,
            'original_language' => 'en',
            'spoken_languages' => null,
            'production_companies' => null,
            'origin_country' => null,
            'content_ratings' => null,
            'alternative_titles' => null,
            'homepage' => null,
            'in_production' => null,
            'thetvdb_id' => $show->thetvdb_id,
        ],
    ]);

    $show->refresh();

    expect($show->tmdb_id)->toBe(1396)
        ->and($show->overview)->toBe('Test overview')
        ->and($show->tmdb_synced_at)->not->toBeNull();
});

it('does not include thetvdb_id in mapped data', function () {
    $details = [
        'id' => 1396,
        'overview' => 'Test',
        'tagline' => '',
        'original_name' => 'Test',
        'original_language' => 'en',
        'content_ratings' => ['results' => []],
        'alternative_titles' => ['results' => []],
        'external_ids' => ['tvdb_id' => 81189],
    ];

    $mapped = UpsertTMDBShowData::mapFromApi($details);

    expect($mapped)->not->toHaveKey('thetvdb_id');
});
