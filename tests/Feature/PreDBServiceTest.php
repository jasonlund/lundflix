<?php

use App\Enums\ReleaseQuality;
use App\Models\Movie;
use App\Services\PreDBService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

function fakePredbSearch(array $releases = []): array
{
    return ['api.predb.net*' => Http::response([
        'status' => 'success',
        'message' => '',
        'data' => $releases,
        'results' => count($releases),
        'time' => 0.05,
    ])];
}

function fakePredbRelease(string $name, int $status = 0): array
{
    return [
        'id' => fake()->numberBetween(1000000, 9999999),
        'pretime' => now()->subDays(2)->timestamp,
        'release' => $name,
        'section' => 'X264',
        'files' => 42,
        'size' => 1500.0,
        'status' => $status,
        'reason' => '',
        'group' => 'GROUP',
        'genre' => '',
        'url' => '/rls/'.$name,
    ];
}

it('returns true when a release exists', function () {
    $movie = Movie::factory()->create(['title' => 'The Dark Knight', 'year' => 2008]);

    Http::fake(fakePredbSearch([
        fakePredbRelease('The.Dark.Knight.2008.1080p.WEB-DL.DD5.1.H.264-GROUP'),
    ]));

    $service = new PreDBService;
    expect($service->hasQualityRelease($movie))->toBeTrue();
});

it('skips nuked releases', function () {
    $movie = Movie::factory()->create(['title' => 'The Dark Knight', 'year' => 2008]);

    Http::fake(fakePredbSearch([
        fakePredbRelease('The.Dark.Knight.2008.1080p.BluRay.x264-GROUP', status: 1),
    ]));

    $service = new PreDBService;
    expect($service->hasQualityRelease($movie))->toBeFalse();
});

it('accepts unnuked releases', function () {
    $movie = Movie::factory()->create(['title' => 'The Dark Knight', 'year' => 2008]);

    Http::fake(fakePredbSearch([
        fakePredbRelease('The.Dark.Knight.2008.1080p.BluRay.x264-GROUP', status: 2),
    ]));

    $service = new PreDBService;
    expect($service->hasQualityRelease($movie))->toBeTrue();
});

it('returns false when no releases found', function () {
    $movie = Movie::factory()->create(['title' => 'Nonexistent Movie', 'year' => 2099]);

    Http::fake(fakePredbSearch([]));

    $service = new PreDBService;
    expect($service->hasQualityRelease($movie))->toBeFalse();
});

it('returns false when API returns error status', function () {
    $movie = Movie::factory()->create(['title' => 'The Dark Knight', 'year' => 2008]);

    Http::fake([
        'api.predb.net*' => Http::response([
            'status' => 'error',
            'message' => 'Something went wrong',
            'data' => null,
        ]),
    ]);

    $service = new PreDBService;
    expect($service->hasQualityRelease($movie))->toBeFalse();
});

it('returns false when movie has empty title', function () {
    $movie = Movie::factory()->create(['title' => '', 'year' => 2008]);

    $service = new PreDBService;
    expect($service->hasQualityRelease($movie))->toBeFalse();

    Http::assertNothingSent();
});

it('sends tag exclusions in the request', function () {
    $movie = Movie::factory()->create(['title' => 'Test Movie', 'year' => 2024]);

    Http::fake(fakePredbSearch([]));

    $service = new PreDBService;
    $service->hasQualityRelease($movie);

    Http::assertSent(function ($request) {
        $tag = $request->data()['tag'] ?? '';

        return str_contains($tag, '-CAM')
            && str_contains($tag, '-TS')
            && str_contains($tag, '-TELESYNC')
            && str_contains($tag, '-HDCAM')
            && str_contains($tag, '-SCR')
            && str_contains($tag, '-SCREENER');
    });
});

describe('highestQuality', function () {
    it('returns the highest quality from multiple releases', function () {
        $movie = Movie::factory()->create(['title' => 'The Dark Knight', 'year' => 2008]);

        Http::fake(fakePredbSearch([
            fakePredbRelease('The.Dark.Knight.2008.1080p.WEB-DL.DD5.1.H.264-GROUP'),
            fakePredbRelease('The.Dark.Knight.2008.1080p.BluRay.x264-GROUP'),
            fakePredbRelease('The.Dark.Knight.2008.720p.WEBRip.x264-GROUP'),
        ]));

        $service = new PreDBService;
        expect($service->highestQuality($movie))->toBe(ReleaseQuality::BluRay);
    });

    it('skips nuked releases when finding highest quality', function () {
        $movie = Movie::factory()->create(['title' => 'The Dark Knight', 'year' => 2008]);

        Http::fake(fakePredbSearch([
            fakePredbRelease('The.Dark.Knight.2008.1080p.BluRay.x264-GROUP', status: 1),
            fakePredbRelease('The.Dark.Knight.2008.1080p.WEB-DL.DD5.1.H.264-GROUP'),
        ]));

        $service = new PreDBService;
        expect($service->highestQuality($movie))->toBe(ReleaseQuality::WEBDL);
    });

    it('returns null when no releases found', function () {
        $movie = Movie::factory()->create(['title' => 'Nonexistent Movie', 'year' => 2099]);

        Http::fake(fakePredbSearch([]));

        $service = new PreDBService;
        expect($service->highestQuality($movie))->toBeNull();
    });

    it('returns null when movie has empty title', function () {
        $movie = Movie::factory()->create(['title' => '', 'year' => 2008]);

        $service = new PreDBService;
        expect($service->highestQuality($movie))->toBeNull();

        Http::assertNothingSent();
    });

    it('skips releases with unrecognized quality tags', function () {
        $movie = Movie::factory()->create(['title' => 'Test Movie', 'year' => 2024]);

        Http::fake(fakePredbSearch([
            fakePredbRelease('Test.Movie.2024.Unknown.Format-GROUP'),
            fakePredbRelease('Test.Movie.2024.720p.HDTV.x264-GROUP'),
        ]));

        $service = new PreDBService;
        expect($service->highestQuality($movie))->toBe(ReleaseQuality::HDTV);
    });

    it('includes unnuked releases', function () {
        $movie = Movie::factory()->create(['title' => 'The Dark Knight', 'year' => 2008]);

        Http::fake(fakePredbSearch([
            fakePredbRelease('The.Dark.Knight.2008.1080p.BluRay.x264-GROUP', status: 2),
        ]));

        $service = new PreDBService;
        expect($service->highestQuality($movie))->toBe(ReleaseQuality::BluRay);
    });
});

describe('buildQuery', function () {
    it('converts title and year to dot-separated format', function () {
        $movie = Movie::factory()->create(['title' => 'The Dark Knight', 'year' => 2008]);

        $service = new PreDBService;
        expect($service->buildQuery($movie))->toBe('The.Dark.Knight.2008');
    });

    it('strips special characters', function () {
        $movie = Movie::factory()->create(['title' => 'Spider-Man: No Way Home', 'year' => 2021]);

        $service = new PreDBService;
        expect($service->buildQuery($movie))->toBe('Spider-Man.No.Way.Home.2021');
    });

    it('handles titles without year', function () {
        $movie = Movie::factory()->create(['title' => 'Inception', 'year' => null]);

        $service = new PreDBService;
        expect($service->buildQuery($movie))->toBe('Inception');
    });

    it('returns null for empty title', function () {
        $movie = Movie::factory()->create(['title' => '']);

        $service = new PreDBService;
        expect($service->buildQuery($movie))->toBeNull();
    });
});
