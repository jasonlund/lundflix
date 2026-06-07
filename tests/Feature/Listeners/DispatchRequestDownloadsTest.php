<?php

use App\Enums\RequestItemStatus;
use App\Events\RequestSubmitted;
use App\Exceptions\IptorrentsAuthException;
use App\Exceptions\IptorrentsRateLimitExceededException;
use App\Jobs\DownloadTorrents;
use App\Listeners\DispatchRequestDownloads;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Show;
use App\Notifications\MultiSeasonPackReviewNotification;
use App\Notifications\TorrentNotFoundNotification;
use App\Notifications\TorrentOversizeNotification;
use App\Services\Torrent\PlanResult;
use App\Services\Torrent\RequestDownloadPlanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'services.slack.enabled' => true,
        'services.slack.notifications.channel' => 'C-DEFAULT',
    ]);
});

function mockListenerPlanner(PlanResult $plan): void
{
    $mock = Mockery::mock(RequestDownloadPlanner::class);
    $mock->shouldReceive('plan')->andReturn($plan);
    app()->instance(RequestDownloadPlanner::class, $mock);
}

function mockThrowingListenerPlanner(Throwable $exception): void
{
    $mock = Mockery::mock(RequestDownloadPlanner::class);
    $mock->shouldReceive('plan')->andThrow($exception);
    app()->instance(RequestDownloadPlanner::class, $mock);
}

function makeListenerRequestWithMovie(): Request
{
    $request = Request::factory()->create();
    $movie = Movie::factory()->create();
    RequestItem::factory()->forRequestable($movie)->create(['request_id' => $request->id]);

    return $request->fresh(['items']);
}

function capturePlannedRequest(): Closure
{
    $captured = new stdClass;
    $mock = Mockery::mock(RequestDownloadPlanner::class);
    $mock->shouldReceive('plan')
        ->andReturnUsing(function (Request $request) use ($captured): PlanResult {
            $captured->request = $request;

            return PlanResult::empty();
        });
    app()->instance(RequestDownloadPlanner::class, $mock);

    return fn (): Request => $captured->request;
}

it('eager-loads show.episodes for episode items', function () {
    Queue::fake();
    Notification::fake();

    $show = Show::factory()->create();
    $episode = Episode::factory()->create(['show_id' => $show->id]);
    $request = Request::factory()->create();
    RequestItem::factory()->forRequestable($episode)->create(['request_id' => $request->id]);

    $getRequest = capturePlannedRequest();

    app(DispatchRequestDownloads::class)->handle(new RequestSubmitted($request->fresh()));

    $loaded = $getRequest()->items->first()->requestable;

    expect($loaded->relationLoaded('show'))->toBeTrue()
        ->and($loaded->show->relationLoaded('episodes'))->toBeTrue();
});

it('does not issue the episode morph constraint for movie-only requests', function () {
    Queue::fake();
    Notification::fake();

    $request = Request::factory()->create();
    $movie = Movie::factory()->create();
    RequestItem::factory()->forRequestable($movie)->create(['request_id' => $request->id]);

    $getRequest = capturePlannedRequest();

    app(DispatchRequestDownloads::class)->handle(new RequestSubmitted($request->fresh()));

    expect($getRequest()->items->first()->relationLoaded('requestable'))->toBeTrue();
});

it('is registered as a listener for RequestSubmitted', function () {
    Event::fake([RequestSubmitted::class]);

    Event::assertListening(RequestSubmitted::class, DispatchRequestDownloads::class);
});

it('implements ShouldQueue', function () {
    expect(DispatchRequestDownloads::class)->toImplement(ShouldQueue::class);
});

it('does nothing for empty request', function () {
    Queue::fake();
    Notification::fake();

    mockListenerPlanner(PlanResult::empty());

    $request = Request::factory()->create();

    app(DispatchRequestDownloads::class)->handle(new RequestSubmitted($request));

    Queue::assertNothingPushed();
    Notification::assertNothingSent();
});

it('dispatches DownloadTorrents when planner returns downloads', function () {
    Queue::fake();
    Notification::fake();

    mockListenerPlanner(new PlanResult(
        downloads: [['torrent_id' => 1, 'filename' => 'a.torrent']],
        notFound: [],
        oversize: [],
        multiSeasonReview: [],
        packCovered: [],
    ));

    $request = makeListenerRequestWithMovie();

    app(DispatchRequestDownloads::class)->handle(new RequestSubmitted($request));

    Queue::assertPushed(DownloadTorrents::class, function (DownloadTorrents $job): bool {
        return $job->torrents === [['torrent_id' => 1, 'filename' => 'a.torrent']];
    });
});

it('does not dispatch DownloadTorrents when no downloads', function () {
    Queue::fake();
    Notification::fake();

    mockListenerPlanner(PlanResult::empty());

    $request = makeListenerRequestWithMovie();

    app(DispatchRequestDownloads::class)->handle(new RequestSubmitted($request));

    Queue::assertNotPushed(DownloadTorrents::class);
});

it('releases for 60 seconds and dispatches nothing on IPT rate limit', function () {
    Queue::fake();
    Notification::fake();

    mockThrowingListenerPlanner(new IptorrentsRateLimitExceededException);

    $request = makeListenerRequestWithMovie();

    $job = Mockery::mock(Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('release')->once()->with(60);
    $job->shouldReceive('fail')->never();

    $listener = app(DispatchRequestDownloads::class);
    $listener->setJob($job);
    $listener->handle(new RequestSubmitted($request));

    Queue::assertNothingPushed();
    Notification::assertNothingSent();
});

it('fails without retry and dispatches nothing on IPT auth error', function () {
    Queue::fake();
    Notification::fake();

    $exception = new IptorrentsAuthException('IPTorrents auth failed');
    mockThrowingListenerPlanner($exception);

    $request = makeListenerRequestWithMovie();

    $job = Mockery::mock(Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('fail')->once()->with($exception);
    $job->shouldReceive('release')->never();

    $listener = app(DispatchRequestDownloads::class);
    $listener->setJob($job);
    $listener->handle(new RequestSubmitted($request));

    Queue::assertNothingPushed();
    Notification::assertNothingSent();
});

it('marks notFound items as NotFound with actioned_at', function () {
    Queue::fake();
    Notification::fake();

    $request = Request::factory()->create();
    $movie = Movie::factory()->create();
    $item = RequestItem::factory()->forRequestable($movie)->create(['request_id' => $request->id]);

    mockListenerPlanner(new PlanResult(
        downloads: [],
        notFound: [$item],
        oversize: [],
        multiSeasonReview: [],
        packCovered: [],
    ));

    app(DispatchRequestDownloads::class)->handle(new RequestSubmitted($request->fresh()));

    $item->refresh();

    expect($item->status)->toBe(RequestItemStatus::NotFound)
        ->and($item->actioned_at)->not->toBeNull();
});

it('marks oversize items as NotFound', function () {
    Queue::fake();
    Notification::fake();

    $request = Request::factory()->create();
    $movie = Movie::factory()->create();
    $item = RequestItem::factory()->forRequestable($movie)->create(['request_id' => $request->id]);

    mockListenerPlanner(new PlanResult(
        downloads: [],
        notFound: [],
        oversize: [['item' => $item, 'maxBytes' => 15 * 1024 ** 3]],
        multiSeasonReview: [],
        packCovered: [],
    ));

    app(DispatchRequestDownloads::class)->handle(new RequestSubmitted($request->fresh()));

    $item->refresh();

    expect($item->status)->toBe(RequestItemStatus::NotFound);
});

it('leaves packCovered items as Pending', function () {
    Queue::fake();
    Notification::fake();

    $show = Show::factory()->create();
    $episode = Episode::factory()->create(['show_id' => $show->id]);
    $request = Request::factory()->create();
    $item = RequestItem::factory()->forRequestable($episode)->create(['request_id' => $request->id]);

    mockListenerPlanner(new PlanResult(
        downloads: [['torrent_id' => 1, 'filename' => 'p.torrent']],
        notFound: [],
        oversize: [],
        multiSeasonReview: [],
        packCovered: [$item],
    ));

    app(DispatchRequestDownloads::class)->handle(new RequestSubmitted($request->fresh()));

    $item->refresh();

    expect($item->status)->toBe(RequestItemStatus::Pending);
});

it('fires TorrentNotFoundNotification only when notFound is non-empty', function () {
    Queue::fake();
    Notification::fake();

    $request = Request::factory()->create();
    $movie = Movie::factory()->create();
    $item = RequestItem::factory()->forRequestable($movie)->create(['request_id' => $request->id]);

    mockListenerPlanner(new PlanResult(
        downloads: [],
        notFound: [$item],
        oversize: [],
        multiSeasonReview: [],
        packCovered: [],
    ));

    app(DispatchRequestDownloads::class)->handle(new RequestSubmitted($request->fresh()));

    Notification::assertSentOnDemand(TorrentNotFoundNotification::class);
    Notification::assertNotSentTo(new Illuminate\Notifications\AnonymousNotifiable, TorrentOversizeNotification::class);
    Notification::assertNotSentTo(new Illuminate\Notifications\AnonymousNotifiable, MultiSeasonPackReviewNotification::class);
});

it('fires TorrentOversizeNotification only when oversize is non-empty', function () {
    Queue::fake();
    Notification::fake();

    $request = Request::factory()->create();
    $movie = Movie::factory()->create();
    $item = RequestItem::factory()->forRequestable($movie)->create(['request_id' => $request->id]);

    mockListenerPlanner(new PlanResult(
        downloads: [],
        notFound: [],
        oversize: [['item' => $item, 'maxBytes' => 15 * 1024 ** 3]],
        multiSeasonReview: [],
        packCovered: [],
    ));

    app(DispatchRequestDownloads::class)->handle(new RequestSubmitted($request->fresh()));

    Notification::assertSentOnDemand(TorrentOversizeNotification::class);
    Notification::assertNotSentTo(new Illuminate\Notifications\AnonymousNotifiable, TorrentNotFoundNotification::class);
});

it('fires MultiSeasonPackReviewNotification only when multiSeasonReview is non-empty', function () {
    Queue::fake();
    Notification::fake();

    $show = Show::factory()->create();
    $episode = Episode::factory()->create(['show_id' => $show->id]);
    $request = Request::factory()->create();
    $item = RequestItem::factory()->forRequestable($episode)->create(['request_id' => $request->id]);

    mockListenerPlanner(new PlanResult(
        downloads: [],
        notFound: [$item],
        oversize: [],
        multiSeasonReview: [[
            'show' => $show,
            'season' => 1,
            'pack' => [
                'torrent_id' => 99,
                'name' => 'Complete Series',
                'size' => '80 GB',
                'seeders' => 1,
                'leechers' => 0,
                'snatches' => 0,
                'uploaded' => 'now',
                'download_url' => 'https://iptorrents.com/download.php/99/p.torrent',
            ],
            'requestItems' => [$item],
        ]],
        packCovered: [],
    ));

    app(DispatchRequestDownloads::class)->handle(new RequestSubmitted($request->fresh()));

    Notification::assertSentOnDemand(MultiSeasonPackReviewNotification::class);
});

it('does not fire notifications when slack is disabled', function () {
    Queue::fake();
    Notification::fake();
    config(['services.slack.enabled' => false]);

    $request = Request::factory()->create();
    $movie = Movie::factory()->create();
    $item = RequestItem::factory()->forRequestable($movie)->create(['request_id' => $request->id]);

    mockListenerPlanner(new PlanResult(
        downloads: [['torrent_id' => 1, 'filename' => 'a.torrent']],
        notFound: [$item],
        oversize: [],
        multiSeasonReview: [],
        packCovered: [],
    ));

    app(DispatchRequestDownloads::class)->handle(new RequestSubmitted($request->fresh()));

    Notification::assertNothingSent();
    Queue::assertPushed(DownloadTorrents::class);
});

it('logs warning and skips notification when channel is not configured', function () {
    Queue::fake();
    Notification::fake();
    Log::spy();
    config(['services.slack.notifications.channel' => null]);

    $request = Request::factory()->create();
    $movie = Movie::factory()->create();
    $item = RequestItem::factory()->forRequestable($movie)->create(['request_id' => $request->id]);

    mockListenerPlanner(new PlanResult(
        downloads: [],
        notFound: [$item],
        oversize: [],
        multiSeasonReview: [],
        packCovered: [],
    ));

    app(DispatchRequestDownloads::class)->handle(new RequestSubmitted($request->fresh()));

    Notification::assertNothingSent();
    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($message): bool => $message === 'Slack notification skipped: channel not configured')
        ->once();
});
