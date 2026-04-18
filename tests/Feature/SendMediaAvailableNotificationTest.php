<?php

use App\Enums\ReleaseQuality;
use App\Events\MediaAvailable;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Request;
use App\Models\Show;
use App\Notifications\MediaAvailableNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.slack.enabled' => true, 'services.slack.notifications.channel' => 'U12345']);
});

it('sends a slack notification when a movie becomes available', function () {
    Notification::fake();

    $movie = Movie::factory()->create(['title' => 'Inception', 'year' => 2010]);
    $request = Request::factory()->create();

    MediaAvailable::dispatch($request, $movie, null, ReleaseQuality::WEBDL);

    Notification::assertSentOnDemand(MediaAvailableNotification::class, function (MediaAvailableNotification $notification) use ($movie) {
        return $notification->media->is($movie) && $notification->quality === ReleaseQuality::WEBDL;
    });
});

it('sends a slack notification when show episodes become available', function () {
    Notification::fake();

    $show = Show::factory()->create(['name' => 'Severance']);
    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 2,
        'number' => 1,
        'type' => 'regular',
    ]);
    $request = Request::factory()->create();

    MediaAvailable::dispatch($request, $show, collect([$episode]), ReleaseQuality::WEBDL);

    Notification::assertSentOnDemand(MediaAvailableNotification::class);
});

it('skips notification when Slack is disabled', function () {
    Notification::fake();
    config(['services.slack.enabled' => false]);

    $movie = Movie::factory()->create();
    $request = Request::factory()->create();

    MediaAvailable::dispatch($request, $movie, null, null);

    Notification::assertNothingSent();
});

it('skips notification when no channel is configured', function () {
    Notification::fake();
    config(['services.slack.notifications.channel' => null]);

    $movie = Movie::factory()->create();
    $request = Request::factory()->create();

    MediaAvailable::dispatch($request, $movie, null, null);

    Notification::assertNothingSent();
});
