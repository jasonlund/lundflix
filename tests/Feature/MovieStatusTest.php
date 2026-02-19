<?php

use App\Enums\MovieStatus;
use App\Models\Movie;

// --- Direct TMDB status mappings ---

it('resolves canceled status directly', function () {
    $movie = Movie::factory()->withTmdbData()->create(['status' => 'Canceled']);

    expect($movie->status)->toBe(MovieStatus::Canceled);
});

it('resolves post production status directly', function () {
    $movie = Movie::factory()->withTmdbData()->create(['status' => 'Post Production']);

    expect($movie->status)->toBe(MovieStatus::PostProduction);
});

it('resolves in production status directly', function () {
    $movie = Movie::factory()->withTmdbData()->create(['status' => 'In Production']);

    expect($movie->status)->toBe(MovieStatus::InProduction);
});

it('resolves planned status directly', function () {
    $movie = Movie::factory()->withTmdbData()->create(['status' => 'Planned']);

    expect($movie->status)->toBe(MovieStatus::Planned);
});

it('resolves rumored status directly', function () {
    $movie = Movie::factory()->withTmdbData()->create(['status' => 'Rumored']);

    expect($movie->status)->toBe(MovieStatus::Rumored);
});

it('returns null for null status', function () {
    $movie = Movie::factory()->create(['status' => null]);

    expect($movie->status)->toBeNull();
});

it('returns null for unknown status string', function () {
    $movie = Movie::factory()->withTmdbData()->create(['status' => 'SomeUnknownValue']);

    expect($movie->status)->toBeNull();
});

it('stores raw tmdb status string in database', function () {
    $movie = Movie::factory()->withTmdbData()->create(['status' => 'Released']);

    $this->assertDatabaseHas('movies', [
        'id' => $movie->id,
        'status' => 'Released',
    ]);
});

// --- Released: digital or physical release is past ---

it('resolves released when digital_release_date column is past', function () {
    $movie = Movie::factory()->withTmdbData()->create([
        'status' => 'Released',
        'digital_release_date' => '2020-01-01',
        'release_dates' => [
            ['iso_3166_1' => 'US', 'release_dates' => [
                ['type' => 3, 'release_date' => '2019-06-01T00:00:00.000Z'],
            ]],
        ],
    ]);

    expect($movie->status)->toBe(MovieStatus::Released);
});

it('resolves released when digital type in release_dates json is past', function () {
    $movie = Movie::factory()->withTmdbData()->create([
        'status' => 'Released',
        'digital_release_date' => null,
        'release_dates' => [
            ['iso_3166_1' => 'US', 'release_dates' => [
                ['type' => 4, 'release_date' => '2020-06-01T00:00:00.000Z'],
            ]],
        ],
    ]);

    expect($movie->status)->toBe(MovieStatus::Released);
});

it('resolves released when physical type in release_dates json is past', function () {
    $movie = Movie::factory()->withTmdbData()->create([
        'status' => 'Released',
        'digital_release_date' => null,
        'release_dates' => [
            ['iso_3166_1' => 'US', 'release_dates' => [
                ['type' => 3, 'release_date' => '2019-06-01T00:00:00.000Z'],
                ['type' => 5, 'release_date' => '2020-06-01T00:00:00.000Z'],
            ]],
        ],
    ]);

    expect($movie->status)->toBe(MovieStatus::Released);
});

it('resolves released when release_dates json is empty and digital_release_date is past', function () {
    $movie = Movie::factory()->withTmdbData()->create([
        'status' => 'Released',
        'digital_release_date' => '2020-06-01',
        'release_date' => '2020-01-01',
        'release_dates' => [],
    ]);

    expect($movie->status)->toBe(MovieStatus::Released);
});

// --- InTheaters: theatrical is past, no digital/physical yet ---

it('resolves in theaters when theatrical is past and digital_release_date is future', function () {
    $movie = Movie::factory()->withTmdbData()->create([
        'status' => 'Released',
        'digital_release_date' => now()->addMonths(3),
        'release_dates' => [
            ['iso_3166_1' => 'US', 'release_dates' => [
                ['type' => 3, 'release_date' => now()->subMonth()->toIso8601String()],
            ]],
        ],
    ]);

    expect($movie->status)->toBe(MovieStatus::InTheaters);
});

it('resolves in theaters when theatrical is past within 90 days and no digital/physical dates', function () {
    $movie = Movie::factory()->withTmdbData()->create([
        'status' => 'Released',
        'digital_release_date' => null,
        'release_dates' => [
            ['iso_3166_1' => 'US', 'release_dates' => [
                ['type' => 3, 'release_date' => now()->subDays(30)->toIso8601String()],
            ]],
        ],
    ]);

    expect($movie->status)->toBe(MovieStatus::InTheaters);
});

it('resolves in theaters using earliest theatrical across all countries', function () {
    $movie = Movie::factory()->withTmdbData()->create([
        'status' => 'Released',
        'digital_release_date' => now()->addMonths(2),
        'release_dates' => [
            ['iso_3166_1' => 'FR', 'release_dates' => [
                ['type' => 3, 'release_date' => now()->subMonths(2)->toIso8601String()],
            ]],
            ['iso_3166_1' => 'US', 'release_dates' => [
                ['type' => 3, 'release_date' => now()->subMonth()->toIso8601String()],
            ]],
        ],
    ]);

    expect($movie->status)->toBe(MovieStatus::InTheaters);
});

// --- 90-day fallback: theatrical past, no digital/physical, 90+ days ---

it('resolves released via 90-day fallback when theatrical is old and no digital/physical dates', function () {
    $movie = Movie::factory()->withTmdbData()->create([
        'status' => 'Released',
        'digital_release_date' => null,
        'release_dates' => [
            ['iso_3166_1' => 'US', 'release_dates' => [
                ['type' => 3, 'release_date' => '2020-01-01T00:00:00.000Z'],
            ]],
        ],
    ]);

    expect($movie->status)->toBe(MovieStatus::Released);
});

it('stays in theaters when theatrical is 90+ days but future digital date exists', function () {
    $movie = Movie::factory()->withTmdbData()->create([
        'status' => 'Released',
        'digital_release_date' => now()->addMonth(),
        'release_dates' => [
            ['iso_3166_1' => 'US', 'release_dates' => [
                ['type' => 3, 'release_date' => now()->subDays(100)->toIso8601String()],
            ]],
        ],
    ]);

    expect($movie->status)->toBe(MovieStatus::InTheaters);
});

it('stays in theaters when theatrical is 90+ days but future digital type in release_dates json', function () {
    $movie = Movie::factory()->withTmdbData()->create([
        'status' => 'Released',
        'digital_release_date' => null,
        'release_dates' => [
            ['iso_3166_1' => 'US', 'release_dates' => [
                ['type' => 3, 'release_date' => now()->subDays(100)->toIso8601String()],
                ['type' => 4, 'release_date' => now()->addMonth()->toIso8601String()],
            ]],
        ],
    ]);

    expect($movie->status)->toBe(MovieStatus::InTheaters);
});

// --- Upcoming: future theatrical ---

it('resolves upcoming when theatrical date is future', function () {
    $movie = Movie::factory()->withTmdbData()->create([
        'status' => 'Released',
        'digital_release_date' => null,
        'release_date' => now()->addYear(),
        'release_dates' => [
            ['iso_3166_1' => 'US', 'release_dates' => [
                ['type' => 3, 'release_date' => now()->addYear()->toIso8601String()],
            ]],
        ],
    ]);

    expect($movie->status)->toBe(MovieStatus::Upcoming);
});

// --- FestivalRelease: premiere past, no past theatrical ---

it('resolves festival release when premiere is past within 90 days and no theatrical', function () {
    $movie = Movie::factory()->withTmdbData()->create([
        'status' => 'Released',
        'digital_release_date' => null,
        'release_date' => null,
        'release_dates' => [
            ['iso_3166_1' => 'FR', 'release_dates' => [
                ['type' => 1, 'release_date' => now()->subDays(30)->toIso8601String()],
            ]],
        ],
    ]);

    expect($movie->status)->toBe(MovieStatus::FestivalRelease);
});

it('resolves festival release when premiere is old but future theatrical exists', function () {
    $movie = Movie::factory()->withTmdbData()->create([
        'status' => 'Released',
        'digital_release_date' => null,
        'release_date' => null,
        'release_dates' => [
            ['iso_3166_1' => 'US', 'release_dates' => [
                ['type' => 1, 'release_date' => now()->subMonths(6)->toIso8601String()],
                ['type' => 3, 'release_date' => now()->addMonth()->toIso8601String()],
            ]],
        ],
    ]);

    expect($movie->status)->toBe(MovieStatus::FestivalRelease);
});

it('resolves released via 90-day fallback when premiere is old and no theatrical', function () {
    $movie = Movie::factory()->withTmdbData()->create([
        'status' => 'Released',
        'digital_release_date' => null,
        'release_date' => null,
        'release_dates' => [
            ['iso_3166_1' => 'FR', 'release_dates' => [
                ['type' => 1, 'release_date' => '2020-05-01T00:00:00.000Z'],
            ]],
        ],
    ]);

    expect($movie->status)->toBe(MovieStatus::Released);
});

// --- Fallbacks: release_date column and no data ---

it('falls back to release_date column for upcoming when future', function () {
    $movie = Movie::factory()->withTmdbData()->create([
        'status' => 'Released',
        'digital_release_date' => null,
        'release_date' => now()->addYear(),
        'release_dates' => [],
    ]);

    expect($movie->status)->toBe(MovieStatus::Upcoming);
});

it('resolves released when past release_date and no release_dates json', function () {
    $movie = Movie::factory()->withTmdbData()->create([
        'status' => 'Released',
        'digital_release_date' => null,
        'release_date' => '2020-01-01',
        'release_dates' => [],
    ]);

    expect($movie->status)->toBe(MovieStatus::Released);
});

it('falls back to released when no usable dates exist', function () {
    $movie = Movie::factory()->withTmdbData()->create([
        'status' => 'Released',
        'digital_release_date' => null,
        'release_date' => null,
        'release_dates' => [],
    ]);

    expect($movie->status)->toBe(MovieStatus::Released);
});

// --- Edge cases ---

it('uses global dates regardless of origin_country', function () {
    $movie = Movie::factory()->withTmdbData()->create([
        'status' => 'Released',
        'origin_country' => ['US'],
        'digital_release_date' => null,
        'release_dates' => [
            ['iso_3166_1' => 'FR', 'release_dates' => [
                ['type' => 3, 'release_date' => now()->subDays(30)->toIso8601String()],
            ]],
        ],
    ]);

    expect($movie->status)->toBe(MovieStatus::InTheaters);
});

it('prefers theatrical over premiere when both exist', function () {
    $movie = Movie::factory()->withTmdbData()->create([
        'status' => 'Released',
        'digital_release_date' => null,
        'release_dates' => [
            ['iso_3166_1' => 'US', 'release_dates' => [
                ['type' => 3, 'release_date' => '2020-01-01T00:00:00.000Z'],
            ]],
            ['iso_3166_1' => 'FR', 'release_dates' => [
                ['type' => 1, 'release_date' => '2019-05-01T00:00:00.000Z'],
            ]],
        ],
    ]);

    expect($movie->status)->toBe(MovieStatus::Released);
});

it('treats theatrical limited same as theatrical for in theaters', function () {
    $movie = Movie::factory()->withTmdbData()->create([
        'status' => 'Released',
        'digital_release_date' => now()->addMonths(3),
        'release_dates' => [
            ['iso_3166_1' => 'US', 'release_dates' => [
                ['type' => 2, 'release_date' => now()->subMonth()->toIso8601String()],
            ]],
        ],
    ]);

    expect($movie->status)->toBe(MovieStatus::InTheaters);
});
