<?php

use App\Events\MediaAvailable;
use App\Jobs\DownloadTorrents;
use App\Models\Episode;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Show;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Torrent\PlanResult;
use App\Services\Torrent\RequestDownloadPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Pin to 2 PM Eastern so subHours(2) stays within the same calendar day
    $this->travelTo(now('America/New_York')->startOfDay()->addHours(14));

    Http::preventStrayRequests();
    RateLimiter::clear('iptorrents');
    Bus::fake([DownloadTorrents::class]);

    config([
        'services.slack.enabled' => false,
    ]);
});

function fakeShowPlanDownload(string $filename): PlanResult
{
    return new PlanResult(
        downloads: [['torrent_id' => 1, 'filename' => $filename]],
        notFound: [],
        oversize: [],
        multiSeasonReview: [],
        packCovered: [],
    );
}

function mockShowPlanner(\Closure $callback): void
{
    $mock = Mockery::mock(RequestDownloadPlanner::class);
    $callback($mock);
    app()->instance(RequestDownloadPlanner::class, $mock);
}

it('creates a request for episodes with available torrents', function () {
    Event::fake([MediaAvailable::class]);

    mockShowPlanner(function ($mock): void {
        $mock->shouldReceive('plan')
            ->once()
            ->andReturn(fakeShowPlanDownload('Severance.S02E01.1080p.WEB-DL.x264-GROUP.torrent'));
    });

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Severance']);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 2,
        'number' => 1,
        'airdate' => today('America/New_York'),
        'airtime' => now('America/New_York')->subHours(2)->format('H:i'),
    ]);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 2,
        'number' => 2,
        'airdate' => today('America/New_York')->addWeek(),
        'airtime' => now('America/New_York')->subHours(2)->format('H:i'),
    ]);

    $this->artisan('process:show-availability')->assertSuccessful();

    expect(Request::count())->toBe(1);
    expect(RequestItem::count())->toBe(1);

    Event::assertDispatched(MediaAvailable::class);

    Bus::assertDispatched(DownloadTorrents::class, function (DownloadTorrents $job): bool {
        return $job->torrents === [['torrent_id' => 1, 'filename' => 'Severance.S02E01.1080p.WEB-DL.x264-GROUP.torrent']];
    });
});

it('does not request an episode already in subscription_episode', function () {
    Event::fake([MediaAvailable::class]);

    mockShowPlanner(function ($mock): void {
        $mock->shouldNotReceive('plan');
    });

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Lost']);
    $sub = Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => today(),
        'airtime' => now()->subHours(2)->format('H:i'),
    ]);

    DB::table('subscription_episode')->insert([
        'subscription_id' => $sub->id,
        'episode_id' => $episode->id,
        'requested_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('process:show-availability')->assertSuccessful();

    expect(Request::count())->toBe(0);

    Event::assertNotDispatched(MediaAvailable::class);
});

it('skips episodes that aired more than 24 hours ago', function () {
    Event::fake([MediaAvailable::class]);

    mockShowPlanner(function ($mock): void {
        $mock->shouldNotReceive('plan');
    });

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Old Show']);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => today()->subDays(3),
        'airtime' => '20:00',
    ]);

    $this->artisan('process:show-availability')->assertSuccessful();

    Event::assertNotDispatched(MediaAvailable::class);
});

it('plans per subscription when multiple users subscribe to the same show', function () {
    Event::fake([MediaAvailable::class]);

    mockShowPlanner(function ($mock): void {
        $mock->shouldReceive('plan')
            ->times(3)
            ->andReturn(fakeShowPlanDownload('Game.Of.Thrones.S08E01.1080p.WEB-DL.x264-GROUP.torrent'));
    });

    $show = Show::factory()->create(['name' => 'Game Of Thrones']);

    foreach (range(1, 3) as $_) {
        Subscription::factory()->forSubscribable($show)->create([
            'user_id' => User::factory()->create()->id,
        ]);
    }

    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 8,
        'number' => 1,
        'airdate' => today('America/New_York'),
        'airtime' => now('America/New_York')->subHours(2)->format('H:i'),
    ]);

    $this->artisan('process:show-availability')->assertSuccessful();

    expect(Request::count())->toBe(3);

    Event::assertDispatchedTimes(MediaAvailable::class, 1);
});

it('dispatches DownloadTorrents once when multiple subscriptions resolve the same torrent_id', function () {
    Event::fake([MediaAvailable::class]);

    mockShowPlanner(function ($mock): void {
        $mock->shouldReceive('plan')
            ->times(2)
            ->andReturn(fakeShowPlanDownload('Game.Of.Thrones.S08.COMPLETE.1080p.WEB-DL.x264-GROUP.torrent'));
    });

    $show = Show::factory()->create(['name' => 'Game Of Thrones']);

    foreach (range(1, 2) as $_) {
        Subscription::factory()->forSubscribable($show)->create([
            'user_id' => User::factory()->create()->id,
        ]);
    }

    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 8,
        'number' => 1,
        'airdate' => today('America/New_York'),
        'airtime' => now('America/New_York')->subHours(2)->format('H:i'),
    ]);

    $this->artisan('process:show-availability')->assertSuccessful();

    Bus::assertDispatchedTimes(DownloadTorrents::class, 1);
});

it('marks newly requested episodes in the pivot table', function () {
    Event::fake([MediaAvailable::class]);

    mockShowPlanner(function ($mock): void {
        $mock->shouldReceive('plan')
            ->once()
            ->andReturn(fakeShowPlanDownload('The.Wire.S01E01.1080p.WEB-DL.x264-GROUP.torrent'));
    });

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'The Wire']);
    $sub = Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => today('America/New_York'),
        'airtime' => now('America/New_York')->subHours(2)->format('H:i'),
    ]);

    $this->artisan('process:show-availability')->assertSuccessful();

    $pivot = $sub->fresh()->processedEpisodes()->where('episodes.id', $episode->id)->first();

    expect($pivot)->not->toBeNull();
    expect($pivot->pivot->requested_at)->not->toBeNull();
});

it('bails early when the planner throws IptorrentsRateLimitExceededException', function () {
    Event::fake([MediaAvailable::class]);

    mockShowPlanner(function ($mock): void {
        $mock->shouldReceive('plan')->once()->andThrow(new \App\Exceptions\IptorrentsRateLimitExceededException);
    });

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Whatever']);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => today('America/New_York'),
        'airtime' => now('America/New_York')->subHours(2)->format('H:i'),
    ]);

    $this->artisan('process:show-availability')->assertSuccessful();

    Event::assertNotDispatched(MediaAvailable::class);
});

it('puts all aired-window episodes into a single request per subscription', function () {
    Event::fake([MediaAvailable::class]);

    $airtime = now('America/New_York')->subHours(2)->format('H:i');

    mockShowPlanner(function ($mock): void {
        $mock->shouldReceive('plan')
            ->once()
            ->andReturn(fakeShowPlanDownload('Stranger.Things.S01.COMPLETE.1080p.WEB-DL.x264-GROUP.torrent'));
    });

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Stranger Things']);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    foreach (range(1, 4) as $num) {
        Episode::factory()->create([
            'show_id' => $show->id,
            'season' => 1,
            'number' => $num,
            'airdate' => today('America/New_York'),
            'airtime' => $airtime,
        ]);
    }

    $this->artisan('process:show-availability')->assertSuccessful();

    expect(Request::count())->toBe(1);
    expect(RequestItem::count())->toBe(4);

    Event::assertDispatched(MediaAvailable::class);
});

it('still picks up episodes that were already notified by the subscriptions command', function () {
    Event::fake([MediaAvailable::class]);

    mockShowPlanner(function ($mock): void {
        $mock->shouldReceive('plan')
            ->once()
            ->andReturn(fakeShowPlanDownload('Severance.S02E01.1080p.WEB-DL.x264-GROUP.torrent'));
    });

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Severance']);
    $sub = Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 2,
        'number' => 1,
        'airdate' => today('America/New_York'),
        'airtime' => now('America/New_York')->subHours(2)->format('H:i'),
    ]);

    $sub->processedEpisodes()->attach($episode->id, ['notified_at' => now()]);

    $this->artisan('process:show-availability')->assertSuccessful();

    expect(Request::count())->toBe(1);
    expect(RequestItem::count())->toBe(1);

    Event::assertDispatched(MediaAvailable::class);

    $pivot = $sub->fresh()->processedEpisodes()->where('episodes.id', $episode->id)->first();
    expect($pivot->pivot->requested_at)->not->toBeNull();
    expect($pivot->pivot->notified_at)->not->toBeNull();
});

it('does not dispatch MediaAvailable when planner returns no downloads', function () {
    Event::fake([MediaAvailable::class]);

    mockShowPlanner(function ($mock): void {
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
    $show = Show::factory()->create(['name' => 'Severance']);
    $sub = Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 2,
        'number' => 1,
        'airdate' => today('America/New_York'),
        'airtime' => now('America/New_York')->subHours(2)->format('H:i'),
    ]);

    $this->artisan('process:show-availability')->assertSuccessful();

    expect(Request::count())->toBe(1);

    Event::assertNotDispatched(MediaAvailable::class);
    Bus::assertNotDispatched(DownloadTorrents::class);

    // pivot should NOT be updated since no download occurred
    expect($sub->fresh()->processedEpisodes()->wherePivotNotNull('requested_at')->count())->toBe(0);
});
