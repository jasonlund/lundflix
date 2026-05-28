<?php

use App\Enums\RequestItemStatus;
use App\Events\MediaAvailable;
use App\Jobs\DownloadTorrents;
use App\Models\Movie;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Torrent\PlanResult;
use App\Services\Torrent\RequestDownloadPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    RateLimiter::clear('iptorrents');
    Bus::fake([DownloadTorrents::class]);

    config([
        'services.slack.enabled' => false,
    ]);
});

function fakeMoviePlanDownload(string $filename = 'Dune.Part.Two.2024.1080p.WEB-DL.x264-GROUP.torrent'): PlanResult
{
    return new PlanResult(
        downloads: [['torrent_id' => 1, 'filename' => $filename]],
        notFound: [],
        oversize: [],
        multiSeasonReview: [],
        packCovered: [],
    );
}

function mockMoviePlanner(\Closure $callback): void
{
    $mock = Mockery::mock(RequestDownloadPlanner::class);
    $callback($mock);
    app()->instance(RequestDownloadPlanner::class, $mock);
}

it('creates a request, dispatches MediaAvailable, and fulfills the subscription when planner returns a download', function () {
    Event::fake([MediaAvailable::class]);

    mockMoviePlanner(function ($mock): void {
        $mock->shouldReceive('plan')->once()->andReturn(fakeMoviePlanDownload());
    });

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

    Event::assertDispatched(MediaAvailable::class, fn (MediaAvailable $event): bool => $event->media->is($movie));

    Bus::assertDispatched(DownloadTorrents::class, function (DownloadTorrents $job): bool {
        return $job->torrents === [['torrent_id' => 1, 'filename' => 'Dune.Part.Two.2024.1080p.WEB-DL.x264-GROUP.torrent']];
    });
});

it('creates a request and dispatches MediaAvailable when planner returns any download payload', function () {
    Event::fake([MediaAvailable::class]);

    mockMoviePlanner(function ($mock): void {
        $mock->shouldReceive('plan')->once()->andReturn(fakeMoviePlanDownload('Dune.Part.Two.2024.1080p.x265.torrent'));
    });

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
    expect($sub->fresh()->fulfilled_at)->not->toBeNull();

    Event::assertDispatched(MediaAvailable::class, fn (MediaAvailable $event): bool => $event->media->is($movie));

    Bus::assertDispatched(DownloadTorrents::class, function (DownloadTorrents $job): bool {
        return $job->torrents === [['torrent_id' => 1, 'filename' => 'Dune.Part.Two.2024.1080p.x265.torrent']];
    });
});

it('does not dispatch MediaAvailable when planner finds nothing, but still records the request', function () {
    Event::fake([MediaAvailable::class]);

    mockMoviePlanner(function ($mock): void {
        $mock->shouldReceive('plan')->once()->andReturnUsing(function ($request): PlanResult {
            return new PlanResult(
                downloads: [],
                notFound: $request->items->all(),
                oversize: [],
                multiSeasonReview: [],
                packCovered: [],
            );
        });
    });

    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'title' => 'Obscure Film',
        'year' => 2024,
        'digital_release_date' => today(),
        'status' => 'Released',
    ]);
    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    $this->artisan('process:movie-availability')->assertSuccessful();

    expect(Request::count())->toBe(1);
    expect(RequestItem::first()->status)->toBe(RequestItemStatus::NotFound);

    Event::assertNotDispatched(MediaAvailable::class);
    Bus::assertNotDispatched(DownloadTorrents::class);
});

it('skips movies whose digital release is older than the 3-day window', function () {
    Event::fake([MediaAvailable::class]);

    mockMoviePlanner(function ($mock): void {
        $mock->shouldNotReceive('plan');
    });

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

    mockMoviePlanner(function ($mock): void {
        $mock->shouldNotReceive('plan');
    });

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

    mockMoviePlanner(function ($mock): void {
        $mock->shouldNotReceive('plan');
    });

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

    mockMoviePlanner(function ($mock): void {
        $mock->shouldNotReceive('plan');
    });

    $movie = Movie::factory()->create([
        'digital_release_date' => today(),
        'status' => 'Released',
    ]);
    Subscription::factory()->forSubscribable($movie)->create(['fulfilled_at' => now()->subDay()]);

    $this->artisan('process:movie-availability')->assertSuccessful();

    Event::assertNotDispatched(MediaAvailable::class);
});

it('plans per subscription when multiple users subscribe to the same movie', function () {
    Event::fake([MediaAvailable::class]);

    mockMoviePlanner(function ($mock): void {
        $mock->shouldReceive('plan')->times(3)->andReturn(fakeMoviePlanDownload('Popular.Film.2024.1080p.WEB-DL.x264-GROUP.torrent'));
    });

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

it('bails early when the planner throws IptorrentsRateLimitExceededException', function () {
    Event::fake([MediaAvailable::class]);

    mockMoviePlanner(function ($mock): void {
        $mock->shouldReceive('plan')->once()->andThrow(new \App\Exceptions\IptorrentsRateLimitExceededException);
    });

    $movieA = Movie::factory()->create([
        'digital_release_date' => today(),
        'status' => 'Released',
    ]);
    $movieB = Movie::factory()->create([
        'digital_release_date' => today(),
        'status' => 'Released',
    ]);
    Subscription::factory()->forSubscribable($movieA)->create();
    Subscription::factory()->forSubscribable($movieB)->create();

    $this->artisan('process:movie-availability')->assertSuccessful();

    Event::assertNotDispatched(MediaAvailable::class);
    Bus::assertNotDispatched(DownloadTorrents::class);
});

it('marks an item NotFound and skips MediaAvailable when the result is oversize-only', function () {
    Event::fake([MediaAvailable::class]);

    mockMoviePlanner(function ($mock): void {
        $mock->shouldReceive('plan')->once()->andReturnUsing(function ($request): PlanResult {
            return new PlanResult(
                downloads: [],
                notFound: [],
                oversize: $request->items->all(),
                multiSeasonReview: [],
                packCovered: [],
            );
        });
    });

    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'digital_release_date' => today(),
        'status' => 'Released',
    ]);
    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    $this->artisan('process:movie-availability')->assertSuccessful();

    expect(RequestItem::first()->status)->toBe(RequestItemStatus::NotFound);

    Event::assertNotDispatched(MediaAvailable::class);
    Bus::assertNotDispatched(DownloadTorrents::class);
});
