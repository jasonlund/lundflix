<?php

declare(strict_types=1);

use App\Enums\RequestItemStatus;
use App\Exceptions\IptorrentsAuthException;
use App\Exceptions\IptorrentsRateLimitExceededException;
use App\Jobs\DownloadTorrents;
use App\Jobs\ProcessRequest;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Show;
use App\Services\IptorrentsService;
use App\Services\TorrentFulfillmentService;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake([DownloadTorrents::class]);
    resetIptThrottle();
});

function iptResult(string $name, int $torrentId = 1): array
{
    $filename = str_replace(' ', '.', $name);

    return [
        'torrent_id' => $torrentId,
        'name' => $name,
        'size' => '1.5 GB',
        'seeders' => 50,
        'leechers' => 5,
        'snatches' => 100,
        'uploaded' => '2024-01-01',
        'download_url' => "https://iptorrents.com/download.php/{$torrentId}/{$filename}.torrent",
    ];
}

function fulfillment(IptorrentsService $ipt): TorrentFulfillmentService
{
    return new TorrentFulfillmentService($ipt);
}

it('dispatches DownloadTorrents for a movie item that has a torrent', function () {
    $movie = Movie::factory()->create();
    $request = Request::factory()->create();
    RequestItem::factory()->pending()->forRequestable($movie)->create(['request_id' => $request->id]);

    $ipt = $this->mock(IptorrentsService::class);
    $ipt->shouldReceive('searchMovie')->once()->andReturn(iptResult('Some.Movie.2024.1080p.x265'));

    (new ProcessRequest($request))->handle(fulfillment($ipt));

    Bus::assertDispatched(DownloadTorrents::class, fn (DownloadTorrents $job): bool => $job->torrents === [
        ['torrent_id' => 1, 'filename' => 'Some.Movie.2024.1080p.x265.torrent'],
    ]);
});

it('dispatches DownloadTorrents for an episode item that has a torrent', function () {
    $show = Show::factory()->create();
    Episode::factory()->count(3)->for($show)
        ->sequence(['number' => 1], ['number' => 2], ['number' => 3])
        ->create(['season' => 1]);
    $episode = $show->episodes()->orderBy('number')->first();

    $request = Request::factory()->create();
    RequestItem::factory()->pending()->forRequestable($episode)->create(['request_id' => $request->id]);

    $ipt = $this->mock(IptorrentsService::class);
    $ipt->shouldReceive('searchEpisode')->once()->andReturn(iptResult('Some.Show.S01E01.1080p.x265'));

    (new ProcessRequest($request))->handle(fulfillment($ipt));

    Bus::assertDispatched(DownloadTorrents::class, fn (DownloadTorrents $job): bool => $job->torrents === [
        ['torrent_id' => 1, 'filename' => 'Some.Show.S01E01.1080p.x265.torrent'],
    ]);
});

it('dispatches one download per episode when a full season is requested', function () {
    $show = Show::factory()->create();
    $episodes = Episode::factory()->count(4)->for($show)
        ->sequence(['number' => 1], ['number' => 2], ['number' => 3], ['number' => 4])
        ->create(['season' => 3]);

    $request = Request::factory()->create();

    foreach ($episodes as $episode) {
        RequestItem::factory()->pending()->forRequestable($episode)->create(['request_id' => $request->id]);
    }

    $ipt = $this->mock(IptorrentsService::class);
    foreach (range(1, 4) as $num) {
        $ipt->shouldReceive('searchEpisode')
            ->once()
            ->withArgs(fn (Episode $e): bool => $e->number === $num)
            ->andReturn(iptResult("Some.Show.S03E0{$num}.1080p.x265", 40 + $num));
    }

    (new ProcessRequest($request))->handle(fulfillment($ipt));

    Bus::assertDispatched(DownloadTorrents::class, fn (DownloadTorrents $job): bool => $job->torrents === [
        ['torrent_id' => 41, 'filename' => 'Some.Show.S03E01.1080p.x265.torrent'],
        ['torrent_id' => 42, 'filename' => 'Some.Show.S03E02.1080p.x265.torrent'],
        ['torrent_id' => 43, 'filename' => 'Some.Show.S03E03.1080p.x265.torrent'],
        ['torrent_id' => 44, 'filename' => 'Some.Show.S03E04.1080p.x265.torrent'],
    ]);
});

it('batches mixed items into a single DownloadTorrents dispatch', function () {
    $movie = Movie::factory()->create();
    $show = Show::factory()->create();
    Episode::factory()->count(2)->for($show)
        ->sequence(['number' => 1], ['number' => 2])
        ->create(['season' => 1]);
    $episode = $show->episodes()->orderBy('number')->first();

    $request = Request::factory()->create();
    RequestItem::factory()->pending()->forRequestable($movie)->create(['request_id' => $request->id]);
    RequestItem::factory()->pending()->forRequestable($episode)->create(['request_id' => $request->id]);

    $ipt = $this->mock(IptorrentsService::class);
    $ipt->shouldReceive('searchMovie')->once()->andReturn(iptResult('Movie.x265'));
    $ipt->shouldReceive('searchEpisode')->once()->andReturn(iptResult('Show.S01E01.x265'));

    (new ProcessRequest($request))->handle(fulfillment($ipt));

    Bus::assertDispatched(DownloadTorrents::class, function (DownloadTorrents $job): bool {
        $filenames = collect($job->torrents)->pluck('filename')->sort()->values()->all();

        return $filenames === ['Movie.x265.torrent', 'Show.S01E01.x265.torrent'];
    });
});

it('does not dispatch DownloadTorrents when no torrent is found', function () {
    $movie = Movie::factory()->create();
    $request = Request::factory()->create();
    RequestItem::factory()->pending()->forRequestable($movie)->create(['request_id' => $request->id]);

    $ipt = $this->mock(IptorrentsService::class);
    $ipt->shouldReceive('searchMovie')->once()->andReturnNull();

    (new ProcessRequest($request))->handle(fulfillment($ipt));

    Bus::assertNotDispatched(DownloadTorrents::class);
});

it('ignores items that are not pending', function () {
    $movie = Movie::factory()->create();
    $request = Request::factory()->create();
    RequestItem::factory()->fulfilled()->forRequestable($movie)->create(['request_id' => $request->id]);

    $ipt = $this->mock(IptorrentsService::class);
    $ipt->shouldNotReceive('searchMovie');

    (new ProcessRequest($request))->handle(fulfillment($ipt));

    Bus::assertNotDispatched(DownloadTorrents::class);
});

it('releases the job when rate limited', function () {
    $movie = Movie::factory()->create();
    $request = Request::factory()->create();
    RequestItem::factory()->pending()->forRequestable($movie)->create(['request_id' => $request->id]);

    $fulfillment = $this->mock(TorrentFulfillmentService::class);
    $fulfillment->shouldReceive('fulfill')->once()->andThrow(new IptorrentsRateLimitExceededException);

    $fakeJob = Mockery::mock(QueueJob::class);
    $fakeJob->shouldReceive('release')->with(60)->once();

    $job = new ProcessRequest($request);
    $job->setJob($fakeJob);
    $job->handle($fulfillment);

    Bus::assertNotDispatched(DownloadTorrents::class);
});

it('propagates auth exceptions so the job fails', function () {
    $movie = Movie::factory()->create();
    $request = Request::factory()->create();
    RequestItem::factory()->pending()->forRequestable($movie)->create(['request_id' => $request->id]);

    $fulfillment = $this->mock(TorrentFulfillmentService::class);
    $fulfillment->shouldReceive('fulfill')->once()->andThrow(new IptorrentsAuthException('cookie expired'));

    expect(fn () => (new ProcessRequest($request))->handle($fulfillment))
        ->toThrow(IptorrentsAuthException::class);

    Bus::assertNotDispatched(DownloadTorrents::class);
});

it('leaves item status untouched', function () {
    $movie = Movie::factory()->create();
    $request = Request::factory()->create();
    $item = RequestItem::factory()->pending()->forRequestable($movie)->create(['request_id' => $request->id]);

    $ipt = $this->mock(IptorrentsService::class);
    $ipt->shouldReceive('searchMovie')->once()->andReturn(iptResult('Movie.x265'));

    (new ProcessRequest($request))->handle(fulfillment($ipt));

    expect($item->fresh()->status)->toBe(RequestItemStatus::Pending);
});
