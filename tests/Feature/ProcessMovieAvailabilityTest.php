<?php

use App\Events\MediaAvailable;
use App\Models\Movie;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    RateLimiter::clear('predb-api');
});

function fakeMoviePredbOk(string $name = 'The.Film.2024.1080p.WEB-DL.x264-GROUP'): void
{
    Http::fake([
        'api.predb.net*' => Http::response([
            'status' => 'success',
            'message' => '',
            'data' => [[
                'id' => 1,
                'pretime' => now()->timestamp,
                'release' => $name,
                'section' => 'X264',
                'files' => 1,
                'size' => 1.0,
                'status' => 0,
                'reason' => '',
                'group' => 'GROUP',
                'genre' => '',
                'url' => '/rls/'.$name,
            ]],
            'results' => 1,
            'time' => 0.05,
        ]),
    ]);
}

function fakeMoviePredbEmpty(): void
{
    Http::fake([
        'api.predb.net*' => Http::response([
            'status' => 'success', 'message' => '', 'data' => [], 'results' => 0, 'time' => 0.01,
        ]),
    ]);
}

it('creates a request, dispatches MediaAvailable, and fulfills the subscription when PreDB has a quality release', function () {
    Event::fake([MediaAvailable::class]);
    fakeMoviePredbOk('Dune.Part.Two.2024.1080p.WEB-DL.x264-GROUP');

    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'title' => 'Dune Part Two',
        'year' => 2024,
        'digital_release_date' => today(),
        'status' => 'Released',
    ]);
    $sub = Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    $this->artisan('process:movie-availability')->assertSuccessful();

    expect(Request::count())->toBe(1);
    expect(RequestItem::count())->toBe(1);
    expect(RequestItem::first()->requestable_id)->toBe($movie->id);
    expect($sub->fresh()->fulfilled_at)->not->toBeNull();

    Event::assertDispatched(MediaAvailable::class);
});

it('does nothing when PreDB returns no quality release', function () {
    Event::fake([MediaAvailable::class]);
    fakeMoviePredbEmpty();

    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'title' => 'Obscure Film',
        'year' => 2024,
        'digital_release_date' => today(),
        'status' => 'Released',
    ]);
    $sub = Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    $this->artisan('process:movie-availability')->assertSuccessful();

    expect(Request::count())->toBe(0);
    expect($sub->fresh()->fulfilled_at)->toBeNull();

    Event::assertNotDispatched(MediaAvailable::class);
});

it('skips movies whose digital release is older than the 3-day window', function () {
    Event::fake([MediaAvailable::class]);
    Http::fake();

    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'title' => 'Stale Movie',
        'year' => 2024,
        'digital_release_date' => today()->subDays(10),
        'status' => 'Released',
    ]);
    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    $this->artisan('process:movie-availability')->assertSuccessful();

    Http::assertNothingSent();
    expect(Request::count())->toBe(0);
    Event::assertNotDispatched(MediaAvailable::class);
});

it('skips movies whose digital release is in the future', function () {
    Event::fake([MediaAvailable::class]);
    Http::fake();

    $movie = Movie::factory()->create([
        'title' => 'Upcoming',
        'year' => 2025,
        'digital_release_date' => today()->addDays(5),
        'status' => 'Released',
    ]);
    Subscription::factory()->forSubscribable($movie)->create();

    $this->artisan('process:movie-availability')->assertSuccessful();

    Http::assertNothingSent();
    Event::assertNotDispatched(MediaAvailable::class);
});

it('skips unreleased movies', function () {
    Event::fake([MediaAvailable::class]);
    Http::fake();

    $movie = Movie::factory()->create([
        'title' => 'Not Yet',
        'year' => 2025,
        'digital_release_date' => today(),
        'status' => 'In Production',
    ]);
    Subscription::factory()->forSubscribable($movie)->create();

    $this->artisan('process:movie-availability')->assertSuccessful();

    Http::assertNothingSent();
    Event::assertNotDispatched(MediaAvailable::class);
});

it('skips subscriptions already fulfilled', function () {
    Event::fake([MediaAvailable::class]);
    Http::fake();

    $movie = Movie::factory()->create([
        'digital_release_date' => today(),
        'status' => 'Released',
    ]);
    Subscription::factory()->forSubscribable($movie)->create(['fulfilled_at' => now()->subDay()]);

    $this->artisan('process:movie-availability')->assertSuccessful();

    Http::assertNothingSent();
    Event::assertNotDispatched(MediaAvailable::class);
});

it('dedupes API calls when multiple users subscribe to the same movie', function () {
    Event::fake([MediaAvailable::class]);
    fakeMoviePredbOk('Popular.Film.2024.1080p.WEB-DL.x264-GROUP');

    $movie = Movie::factory()->create([
        'title' => 'Popular Film',
        'year' => 2024,
        'digital_release_date' => today(),
        'status' => 'Released',
    ]);

    foreach (range(1, 3) as $_) {
        Subscription::factory()->forSubscribable($movie)->create([
            'user_id' => User::factory()->create()->id,
        ]);
    }

    $this->artisan('process:movie-availability')->assertSuccessful();

    Http::assertSentCount(1);
    expect(Request::count())->toBe(3);

    Event::assertDispatchedTimes(MediaAvailable::class, 1);
});

it('bails early when the PreDB rate limit is reached', function () {
    Event::fake([MediaAvailable::class]);
    Http::fake();

    foreach (range(1, 28) as $_) {
        RateLimiter::hit('predb-api', 60);
    }

    $movie = Movie::factory()->create([
        'digital_release_date' => today(),
        'status' => 'Released',
    ]);
    Subscription::factory()->forSubscribable($movie)->create();

    $this->artisan('process:movie-availability')->assertSuccessful();

    Http::assertNothingSent();
    Event::assertNotDispatched(MediaAvailable::class);
});
