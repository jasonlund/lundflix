<?php

use App\Events\MediaAvailable;
use App\Models\Movie;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Subscription;
use App\Models\User;
use App\Services\IptorrentsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    RateLimiter::clear('iptorrents');
});

function fakeTorrentResult(string $name = 'Dune.Part.Two.2024.1080p.WEB-DL.x264-GROUP'): array
{
    return [
        'torrent_id' => 1,
        'name' => $name,
        'size' => '1.5 GB',
        'seeders' => 50,
        'leechers' => 5,
        'snatches' => 100,
        'uploaded' => '2024-01-01',
        'download_url' => 'https://iptorrents.com/download.php/1/file.torrent',
    ];
}

it('creates a request, dispatches MediaAvailable, and fulfills the subscription when IPTorrents has a torrent', function () {
    Event::fake([MediaAvailable::class]);

    $mock = $this->mock(IptorrentsService::class);
    $mock->shouldReceive('searchMovie')->once()->andReturn(fakeTorrentResult('Dune.Part.Two.2024.1080p.WEB-DL.x264-GROUP'));

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

it('does nothing when IPTorrents returns no results', function () {
    Event::fake([MediaAvailable::class]);

    $mock = $this->mock(IptorrentsService::class);
    $mock->shouldReceive('searchMovie')->once()->andReturnNull();

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

    $mock = $this->mock(IptorrentsService::class);
    $mock->shouldNotReceive('searchMovie');

    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'title' => 'Stale Movie',
        'year' => 2024,
        'digital_release_date' => today()->subDays(10),
        'status' => 'Released',
    ]);
    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    $this->artisan('process:movie-availability')->assertSuccessful();

    expect(Request::count())->toBe(0);
    Event::assertNotDispatched(MediaAvailable::class);
});

it('skips movies whose digital release is in the future', function () {
    Event::fake([MediaAvailable::class]);

    $mock = $this->mock(IptorrentsService::class);
    $mock->shouldNotReceive('searchMovie');

    $movie = Movie::factory()->create([
        'title' => 'Upcoming',
        'year' => 2025,
        'digital_release_date' => today()->addDays(5),
        'status' => 'Released',
    ]);
    Subscription::factory()->forSubscribable($movie)->create();

    $this->artisan('process:movie-availability')->assertSuccessful();

    Event::assertNotDispatched(MediaAvailable::class);
});

it('skips unreleased movies', function () {
    Event::fake([MediaAvailable::class]);

    $mock = $this->mock(IptorrentsService::class);
    $mock->shouldNotReceive('searchMovie');

    $movie = Movie::factory()->create([
        'title' => 'Not Yet',
        'year' => 2025,
        'digital_release_date' => today(),
        'status' => 'In Production',
    ]);
    Subscription::factory()->forSubscribable($movie)->create();

    $this->artisan('process:movie-availability')->assertSuccessful();

    Event::assertNotDispatched(MediaAvailable::class);
});

it('skips subscriptions already fulfilled', function () {
    Event::fake([MediaAvailable::class]);

    $mock = $this->mock(IptorrentsService::class);
    $mock->shouldNotReceive('searchMovie');

    $movie = Movie::factory()->create([
        'digital_release_date' => today(),
        'status' => 'Released',
    ]);
    Subscription::factory()->forSubscribable($movie)->create(['fulfilled_at' => now()->subDay()]);

    $this->artisan('process:movie-availability')->assertSuccessful();

    Event::assertNotDispatched(MediaAvailable::class);
});

it('dedupes API calls when multiple users subscribe to the same movie', function () {
    Event::fake([MediaAvailable::class]);

    $mock = $this->mock(IptorrentsService::class);
    $mock->shouldReceive('searchMovie')->once()->andReturn(fakeTorrentResult('Popular.Film.2024.1080p.WEB-DL.x264-GROUP'));

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

    expect(Request::count())->toBe(3);

    Event::assertDispatchedTimes(MediaAvailable::class, 1);
});

it('bails early when the IPTorrents rate limit is reached', function () {
    Event::fake([MediaAvailable::class]);

    foreach (range(1, 10) as $_) {
        RateLimiter::hit('iptorrents', 60);
    }

    $movie = Movie::factory()->create([
        'digital_release_date' => today(),
        'status' => 'Released',
    ]);
    Subscription::factory()->forSubscribable($movie)->create();

    $this->artisan('process:movie-availability')->assertSuccessful();

    Event::assertNotDispatched(MediaAvailable::class);
});
