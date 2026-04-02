<?php

use App\Events\RequestSubmitted;
use App\Listeners\SendRequestNotification;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Show;
use App\Notifications\RequestItemsNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

it('is registered as a listener for RequestSubmitted', function () {
    Event::fake([RequestSubmitted::class]);

    Event::assertListening(
        RequestSubmitted::class,
        SendRequestNotification::class,
    );
});

it('implements ShouldQueue', function () {
    expect(SendRequestNotification::class)
        ->toImplement(ShouldQueue::class);
});

it('sends slack notification when slack is enabled and channel is configured', function () {
    Notification::fake();
    Log::spy();
    config(['services.slack.enabled' => true, 'services.slack.notifications.channel' => 'C12345']);

    $request = Request::factory()->create();
    $movie = Movie::factory()->create();
    RequestItem::factory()->forRequestable($movie)->create(['request_id' => $request->id]);

    $listener = new SendRequestNotification;
    $listener->handle(new RequestSubmitted($request));

    Notification::assertSentOnDemand(RequestItemsNotification::class);

    Log::shouldHaveReceived('info')
        ->withArgs(fn ($message) => $message === 'Slack notification sent')
        ->once();
});

it('sends notification with episodes', function () {
    Notification::fake();
    Log::spy();
    config(['services.slack.enabled' => true, 'services.slack.notifications.channel' => 'C12345']);

    $show = Show::factory()->create();
    $request = Request::factory()->create();
    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'type' => 'regular',
    ]);
    RequestItem::factory()->forRequestable($episode)->create(['request_id' => $request->id]);

    $listener = new SendRequestNotification;
    $listener->handle(new RequestSubmitted($request));

    Notification::assertSentOnDemand(RequestItemsNotification::class);

    Log::shouldHaveReceived('info')
        ->withArgs(fn ($message) => $message === 'Slack notification sent')
        ->once();
});

it('does not send notification when slack is disabled', function () {
    Notification::fake();
    Log::spy();
    config(['services.slack.enabled' => false]);

    $request = Request::factory()->create();
    $movie = Movie::factory()->create();
    RequestItem::factory()->forRequestable($movie)->create(['request_id' => $request->id]);

    $listener = new SendRequestNotification;
    $listener->handle(new RequestSubmitted($request));

    Notification::assertNothingSent();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($message) => $message === 'Slack notification skipped: Slack is not enabled')
        ->once();
});

it('does not send notification when channel is not configured', function () {
    Notification::fake();
    Log::spy();
    config(['services.slack.enabled' => true, 'services.slack.notifications.channel' => null]);

    $request = Request::factory()->create();
    $movie = Movie::factory()->create();
    RequestItem::factory()->forRequestable($movie)->create(['request_id' => $request->id]);

    $listener = new SendRequestNotification;
    $listener->handle(new RequestSubmitted($request));

    Notification::assertNothingSent();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($message) => $message === 'Slack notification skipped: channel not configured')
        ->once();
});

it('does not send notification when request has no items', function () {
    Notification::fake();
    Log::spy();
    config(['services.slack.enabled' => true, 'services.slack.notifications.channel' => 'C12345']);

    $request = Request::factory()->create();

    $listener = new SendRequestNotification;
    $listener->handle(new RequestSubmitted($request));

    Notification::assertNothingSent();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($message) => $message === 'Slack notification skipped: request has no items')
        ->once();
});
