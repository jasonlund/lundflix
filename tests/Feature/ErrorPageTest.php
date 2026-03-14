<?php

use App\Support\ErrorPageResolver;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Compatibility;

it('has valid error page config', function (int $status) {
    $config = config("error-pages.{$status}");

    expect($config)->toBeArray()
        ->toHaveKeys(['message', 'description', 'videos']);
    expect($config['videos'])->toBeArray()->not->toBeEmpty();

    foreach ($config['videos'] as $video) {
        expect($video)->toHaveKeys(['video', 'type', 'imdb_id']);
        expect($video['type'])->toBeIn(['show', 'movie']);
    }
})->with([401, 403, 404, 419, 500, 503]);

it('resolves a valid error page with all required fields', function (int $status) {
    $page = ErrorPageResolver::resolve($status);

    expect($page)->toBeArray()
        ->toHaveKeys(['status', 'src', 'url', 'message', 'description', 'caption']);
    expect($page['status'])->toBe($status);
    expect($page['src'])->toBeString();
    expect($page['url'])->toBeString();
})->with([401, 403, 404, 419, 500, 503]);

it('displays trace ID on real 500 error page', function () {
    config(['app.debug' => false]);

    $traceId = 'test-trace-id-'.fake()->uuid();
    Compatibility::addTraceIdToContext($traceId);

    Route::get('/test-500', fn () => throw new \RuntimeException('Test error'));

    $response = $this->get('/test-500');

    $response->assertStatus(500);
    $response->assertSee($traceId);
});

it('displays caption on error page when configured', function () {
    Route::get('/test-403', fn () => abort(403));

    $response = $this->get('/test-403');

    $response->assertForbidden();
    $response->assertSee('The pineapple suite');
    $response->assertSee('is occupied');
});

it('does not display caption when not configured', function () {
    Route::get('/test-404', fn () => abort(404));

    $response = $this->get('/test-404');

    $response->assertNotFound();
    $response->assertDontSee('The pineapple suite');
});

it('displays nightwatch trace ID from context on real error page', function () {
    config(['app.debug' => false]);

    Route::get('/test-error', fn () => throw new \RuntimeException('Test error'));

    $response = $this->get('/test-error');

    $response->assertStatus(500);

    $traceId = Compatibility::getTraceIdFromContext();
    $response->assertSee($traceId);
});

it('generates correct route for show type videos', function () {
    $page = ErrorPageResolver::resolve(401);

    expect($page['url'])->toContain('/shows/');
});

it('resolves all error pages with all videos', function () {
    $all = ErrorPageResolver::all();

    expect($all)->toHaveCount(6);

    $all->each(function (array $page, int $code) {
        expect($page)->toHaveKeys(['message', 'description', 'videos']);
        expect($page['message'])->toBeString();
        expect($page['videos'])->toBeArray()->not->toBeEmpty();

        foreach ($page['videos'] as $video) {
            expect($video)->toHaveKeys(['src', 'url', 'caption']);
            expect($video['src'])->toBeString();
            expect($video['url'])->toBeString();
        }
    });
});

it('renders error preview page for configured status codes', function (int $status) {
    $response = $this->get(route('error-preview', $status));

    $response->assertStatus($status);
    $response->assertSee(config("error-pages.{$status}.message"));
})->with([401, 403, 404, 419, 500, 503]);

it('returns 404 for unconfigured status codes on preview route', function () {
    $this->get(route('error-preview', 418))
        ->assertNotFound();
});

it('does not include trace ID on preview pages', function () {
    $response = $this->get(route('error-preview', 500));

    $response->assertStatus(500);
    $response->assertDontSee('Trace ID');
});
