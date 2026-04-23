<?php

use App\Enums\TMDBReleaseType;
use App\Models\Movie;

describe('Movie::meaningfulReleases()', function () {
    it('returns empty collection when release_dates is null', function () {
        $movie = Movie::factory()->create(['release_dates' => null]);

        expect($movie->meaningfulReleases())->toBeEmpty();
    });

    it('returns one entry per release type sorted by type value', function () {
        $movie = Movie::factory()->create([
            'origin_country' => ['US'],
            'release_dates' => [
                [
                    'iso_3166_1' => 'US',
                    'release_dates' => [
                        ['type' => 3, 'release_date' => '2024-06-15T00:00:00.000Z', 'certification' => 'PG-13', 'note' => '', 'iso_639_1' => '', 'descriptors' => []],
                        ['type' => 4, 'release_date' => '2024-09-01T00:00:00.000Z', 'certification' => 'PG-13', 'note' => 'Netflix', 'iso_639_1' => '', 'descriptors' => []],
                    ],
                ],
                [
                    'iso_3166_1' => 'FR',
                    'release_dates' => [
                        ['type' => 1, 'release_date' => '2024-05-20T00:00:00.000Z', 'certification' => '', 'note' => 'Cannes Film Festival', 'iso_639_1' => '', 'descriptors' => []],
                    ],
                ],
            ],
        ]);

        $releases = $movie->meaningfulReleases();

        expect($releases)->toHaveCount(3);
        expect($releases[0]['type'])->toBe(TMDBReleaseType::Premiere);
        expect($releases[1]['type'])->toBe(TMDBReleaseType::Theatrical);
        expect($releases[2]['type'])->toBe(TMDBReleaseType::Digital);
    });

    it('prefers non-origin-country entries for Premiere type', function () {
        $movie = Movie::factory()->create([
            'origin_country' => ['US'],
            'release_dates' => [
                [
                    'iso_3166_1' => 'US',
                    'release_dates' => [
                        ['type' => 1, 'release_date' => '2024-05-10T00:00:00.000Z', 'certification' => '', 'note' => 'LA Premiere', 'iso_639_1' => '', 'descriptors' => []],
                    ],
                ],
                [
                    'iso_3166_1' => 'FR',
                    'release_dates' => [
                        ['type' => 1, 'release_date' => '2024-05-20T00:00:00.000Z', 'certification' => '', 'note' => 'Cannes', 'iso_639_1' => '', 'descriptors' => []],
                    ],
                ],
            ],
        ]);

        $releases = $movie->meaningfulReleases();
        $premiere = $releases->firstWhere('type', TMDBReleaseType::Premiere);

        expect($premiere['country'])->toBe('FR');
        expect($premiere['note'])->toBe('Cannes');
    });

    it('prefers origin-country entries for non-Premiere types', function () {
        $movie = Movie::factory()->create([
            'origin_country' => ['US'],
            'release_dates' => [
                [
                    'iso_3166_1' => 'GB',
                    'release_dates' => [
                        ['type' => 3, 'release_date' => '2024-06-01T00:00:00.000Z', 'certification' => '12A', 'note' => '', 'iso_639_1' => '', 'descriptors' => []],
                    ],
                ],
                [
                    'iso_3166_1' => 'US',
                    'release_dates' => [
                        ['type' => 3, 'release_date' => '2024-06-15T00:00:00.000Z', 'certification' => 'PG-13', 'note' => '', 'iso_639_1' => '', 'descriptors' => []],
                    ],
                ],
            ],
        ]);

        $releases = $movie->meaningfulReleases();
        $theatrical = $releases->firstWhere('type', TMDBReleaseType::Theatrical);

        expect($theatrical['country'])->toBe('US');
        expect($theatrical['certification'])->toBe('PG-13');
    });

    it('returns null for empty certification and note', function () {
        $movie = Movie::factory()->create([
            'origin_country' => ['US'],
            'release_dates' => [
                [
                    'iso_3166_1' => 'US',
                    'release_dates' => [
                        ['type' => 3, 'release_date' => '2024-06-15T00:00:00.000Z', 'certification' => '', 'note' => '', 'iso_639_1' => '', 'descriptors' => []],
                    ],
                ],
            ],
        ]);

        $releases = $movie->meaningfulReleases();

        expect($releases[0]['certification'])->toBeNull();
        expect($releases[0]['note'])->toBeNull();
    });

    it('includes descriptors when present', function () {
        $movie = Movie::factory()->create([
            'origin_country' => ['US'],
            'release_dates' => [
                [
                    'iso_3166_1' => 'US',
                    'release_dates' => [
                        ['type' => 3, 'release_date' => '2024-06-15T00:00:00.000Z', 'certification' => 'R', 'note' => '', 'iso_639_1' => '', 'descriptors' => ['Violence', 'Language']],
                    ],
                ],
            ],
        ]);

        $releases = $movie->meaningfulReleases();

        expect($releases[0]['descriptors'])->toBe(['Violence', 'Language']);
    });

    it('skips entries with empty release_date', function () {
        $movie = Movie::factory()->create([
            'origin_country' => ['US'],
            'release_dates' => [
                [
                    'iso_3166_1' => 'US',
                    'release_dates' => [
                        ['type' => 3, 'release_date' => '', 'certification' => 'R', 'note' => '', 'iso_639_1' => '', 'descriptors' => []],
                    ],
                ],
            ],
        ]);

        expect($movie->meaningfulReleases())->toBeEmpty();
    });

    it('omits non-Premiere release type when only foreign countries have data', function () {
        $movie = Movie::factory()->create([
            'origin_country' => ['US'],
            'release_dates' => [
                [
                    'iso_3166_1' => 'ID',
                    'release_dates' => [
                        ['type' => 4, 'release_date' => '2016-01-07T00:00:00.000Z', 'certification' => '', 'note' => '', 'iso_639_1' => '', 'descriptors' => []],
                    ],
                ],
            ],
        ]);

        $releases = $movie->meaningfulReleases();

        expect($releases->firstWhere('type', TMDBReleaseType::Digital))->toBeNull();
    });

    it('falls back to US for non-Premiere types when origin country has no entry', function () {
        $movie = Movie::factory()->create([
            'origin_country' => ['GB'],
            'release_dates' => [
                [
                    'iso_3166_1' => 'US',
                    'release_dates' => [
                        ['type' => 3, 'release_date' => '2024-06-15T00:00:00.000Z', 'certification' => 'PG-13', 'note' => '', 'iso_639_1' => '', 'descriptors' => []],
                    ],
                ],
                [
                    'iso_3166_1' => 'FR',
                    'release_dates' => [
                        ['type' => 3, 'release_date' => '2024-06-01T00:00:00.000Z', 'certification' => '', 'note' => '', 'iso_639_1' => '', 'descriptors' => []],
                    ],
                ],
            ],
        ]);

        $releases = $movie->meaningfulReleases();
        $theatrical = $releases->firstWhere('type', TMDBReleaseType::Theatrical);

        expect($theatrical['country'])->toBe('US');
    });
});
