<?php

use App\Exceptions\IptorrentsRateLimitExceededException;
use App\Jobs\DownloadTorrents;
use App\Notifications\TorrentIgnoredNotification;
use App\Notifications\TorrentRejectedNotification;
use App\Services\IptorrentsService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    Sleep::fake();
    Log::spy();

    config(['services.slack.enabled' => true]);
    config(['services.slack.notifications.channel' => '#test-channel']);
});

function mockIptDownload(MockInterface $mock, int $torrentId, string $filename): void
{
    $mock->shouldReceive('download')
        ->with($torrentId, $filename)
        ->andReturnUsing(function () use ($filename) {
            Storage::disk('local')->put("private/torrents/{$filename}", 'torrent-content');

            return Storage::disk('local')->path("private/torrents/{$filename}");
        });
}

function mockTorrentDisk(array $existsResponses): MockInterface
{
    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('put')->andReturn(true);

    $disk->shouldReceive('exists')->andReturnUsing(function (string $path) use ($existsResponses): bool {
        return $existsResponses[$path] ?? false;
    });

    Storage::set('torrent', $disk);

    return $disk;
}

it('detects acceptance when file disappears during poll', function () {
    $ipt = $this->mock(IptorrentsService::class);
    mockIptDownload($ipt, 123, 'Movie.2024.torrent');

    mockTorrentDisk([
        'Movie.2024.torrent.invalid' => false,
        'Movie.2024.torrent' => false,
    ]);

    $job = new DownloadTorrents([
        ['torrent_id' => 123, 'filename' => 'Movie.2024.torrent'],
    ]);
    $job->handle(app(IptorrentsService::class));

    Storage::disk('local')->assertMissing('private/torrents/Movie.2024.torrent');

    Notification::assertNothingSent();
});

it('detects rejection when file is renamed to .invalid', function () {
    $ipt = $this->mock(IptorrentsService::class);
    mockIptDownload($ipt, 456, 'Bad.Torrent.torrent');

    mockTorrentDisk([
        'Bad.Torrent.torrent.invalid' => true,
        'Bad.Torrent.torrent' => true,
    ]);

    $job = new DownloadTorrents([
        ['torrent_id' => 456, 'filename' => 'Bad.Torrent.torrent'],
    ]);
    $job->handle(app(IptorrentsService::class));

    Notification::assertSentOnDemand(TorrentRejectedNotification::class, function (TorrentRejectedNotification $notification, array $channels, object $notifiable) {
        return $notification->filenames === ['Bad.Torrent.torrent']
            && $notifiable->routes['slack'] === '#test-channel';
    });

    Log::shouldHaveReceived('info')->withArgs(fn (string $message): bool => $message === 'Torrent rejected by client');
});

it('detects timeout when file remains after all polls', function () {
    $ipt = $this->mock(IptorrentsService::class);
    mockIptDownload($ipt, 789, 'Stale.Torrent.torrent');

    mockTorrentDisk([
        'Stale.Torrent.torrent.invalid' => false,
        'Stale.Torrent.torrent' => true,
    ]);

    $job = new DownloadTorrents([
        ['torrent_id' => 789, 'filename' => 'Stale.Torrent.torrent'],
    ]);
    $job->handle(app(IptorrentsService::class));

    Notification::assertSentOnDemand(TorrentIgnoredNotification::class, function (TorrentIgnoredNotification $notification, array $channels, object $notifiable) {
        return $notification->filenames === ['Stale.Torrent.torrent']
            && $notifiable->routes['slack'] === '#test-channel';
    });

    Notification::assertSentOnDemandTimes(TorrentRejectedNotification::class, 0);

    Log::shouldHaveReceived('info')->withArgs(fn (string $message): bool => $message === 'Torrent not picked up by client');

    Sleep::assertSleptTimes(12);
});

it('processes multiple files with mixed outcomes and sends batched notifications', function () {
    $ipt = $this->mock(IptorrentsService::class);
    mockIptDownload($ipt, 1, 'accepted.torrent');
    mockIptDownload($ipt, 2, 'rejected.torrent');
    mockIptDownload($ipt, 3, 'ignored.torrent');

    mockTorrentDisk([
        'accepted.torrent.invalid' => false,
        'accepted.torrent' => false,
        'rejected.torrent.invalid' => true,
        'rejected.torrent' => true,
        'ignored.torrent.invalid' => false,
        'ignored.torrent' => true,
    ]);

    $job = new DownloadTorrents([
        ['torrent_id' => 1, 'filename' => 'accepted.torrent'],
        ['torrent_id' => 2, 'filename' => 'rejected.torrent'],
        ['torrent_id' => 3, 'filename' => 'ignored.torrent'],
    ]);
    $job->handle(app(IptorrentsService::class));

    Notification::assertSentOnDemandTimes(TorrentRejectedNotification::class, 1);
    Notification::assertSentOnDemandTimes(TorrentIgnoredNotification::class, 1);

    Notification::assertSentOnDemand(TorrentRejectedNotification::class, function (TorrentRejectedNotification $notification) {
        return $notification->filenames === ['rejected.torrent'];
    });

    Notification::assertSentOnDemand(TorrentIgnoredNotification::class, function (TorrentIgnoredNotification $notification) {
        return $notification->filenames === ['ignored.torrent'];
    });
});

it('continues processing when FTP upload fails for one file', function () {
    $ipt = $this->mock(IptorrentsService::class);
    mockIptDownload($ipt, 1, 'fail.torrent');
    mockIptDownload($ipt, 2, 'success.torrent');

    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('put')
        ->with('fail.torrent', 'torrent-content')
        ->andThrow(new RuntimeException('FTP write error'));
    $disk->shouldReceive('put')
        ->with('success.torrent', 'torrent-content')
        ->andReturn(true);
    $disk->shouldReceive('exists')
        ->with('success.torrent.invalid')
        ->andReturn(false);
    $disk->shouldReceive('exists')
        ->with('success.torrent')
        ->andReturn(false);
    Storage::set('torrent', $disk);

    $job = new DownloadTorrents([
        ['torrent_id' => 1, 'filename' => 'fail.torrent'],
        ['torrent_id' => 2, 'filename' => 'success.torrent'],
    ]);
    $job->handle(app(IptorrentsService::class));

    Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => $message === 'Torrent processing failed');

    Notification::assertNothingSent();
});

it('does not send notifications when slack is disabled', function () {
    config(['services.slack.enabled' => false]);

    $ipt = $this->mock(IptorrentsService::class);
    mockIptDownload($ipt, 1, 'test.torrent');

    mockTorrentDisk([
        'test.torrent.invalid' => true,
        'test.torrent' => true,
    ]);

    $job = new DownloadTorrents([
        ['torrent_id' => 1, 'filename' => 'test.torrent'],
    ]);
    $job->handle(app(IptorrentsService::class));

    Notification::assertNothingSent();
});

it('releases the job with delay when rate limited on first torrent', function () {
    $ipt = $this->mock(IptorrentsService::class);
    $ipt->shouldReceive('download')
        ->with(1, 'first.torrent')
        ->andThrow(new IptorrentsRateLimitExceededException);

    $fakeJob = Mockery::mock(QueueJob::class);
    $fakeJob->shouldReceive('release')->with(60)->once();

    $job = new DownloadTorrents([
        ['torrent_id' => 1, 'filename' => 'first.torrent'],
        ['torrent_id' => 2, 'filename' => 'second.torrent'],
    ]);
    $job->setJob($fakeJob);
    $job->handle(app(IptorrentsService::class));

    expect($job->torrents)->toBe([
        ['torrent_id' => 1, 'filename' => 'first.torrent'],
        ['torrent_id' => 2, 'filename' => 'second.torrent'],
    ]);

    Notification::assertNothingSent();
});

it('releases with remaining torrents and sends notifications for already-processed ones', function () {
    $ipt = $this->mock(IptorrentsService::class);
    mockIptDownload($ipt, 1, 'accepted.torrent');
    $ipt->shouldReceive('download')
        ->with(2, 'ratelimited.torrent')
        ->andThrow(new IptorrentsRateLimitExceededException);

    mockTorrentDisk([
        'accepted.torrent.invalid' => false,
        'accepted.torrent' => false,
    ]);

    $fakeJob = Mockery::mock(QueueJob::class);
    $fakeJob->shouldReceive('release')->with(60)->once();

    $job = new DownloadTorrents([
        ['torrent_id' => 1, 'filename' => 'accepted.torrent'],
        ['torrent_id' => 2, 'filename' => 'ratelimited.torrent'],
        ['torrent_id' => 3, 'filename' => 'pending.torrent'],
    ]);
    $job->setJob($fakeJob);
    $job->handle(app(IptorrentsService::class));

    expect($job->torrents)->toBe([
        ['torrent_id' => 2, 'filename' => 'ratelimited.torrent'],
        ['torrent_id' => 3, 'filename' => 'pending.torrent'],
    ]);

    Notification::assertNothingSent();
});
