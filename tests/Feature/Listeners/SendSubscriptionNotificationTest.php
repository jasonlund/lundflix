<?php

use App\Events\SubscriptionTriggered;
use App\Listeners\SendSubscriptionNotification;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Request;
use App\Models\Show;
use App\Notifications\SubscriptionMediaNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

it('is registered as a listener for SubscriptionTriggered', function () {
    Event::fake([SubscriptionTriggered::class]);

    Event::assertListening(
        SubscriptionTriggered::class,
        SendSubscriptionNotification::class,
    );
});

it('implements ShouldQueue', function () {
    expect(SendSubscriptionNotification::class)
        ->toImplement(ShouldQueue::class);
});

it('sends slack notification for a movie subscription', function () {
    Notification::fake();
    config(['services.slack.enabled' => true, 'services.slack.notifications.channel' => 'C12345']);

    $request = Request::factory()->create();
    $movie = Movie::factory()->create(['title' => 'Dune: Part Two', 'year' => 2024]);

    $listener = new SendSubscriptionNotification;
    $listener->handle(new SubscriptionTriggered($request, $movie));

    Notification::assertSentOnDemand(SubscriptionMediaNotification::class);
});

it('sends slack notification for a show subscription with episodes', function () {
    Notification::fake();
    config(['services.slack.enabled' => true, 'services.slack.notifications.channel' => 'C12345']);

    $request = Request::factory()->create();
    $show = Show::factory()->create(['name' => 'Stranger Things']);
    $episodes = collect([
        Episode::factory()->create(['show_id' => $show->id, 'season' => 6, 'number' => 1, 'type' => 'regular']),
        Episode::factory()->create(['show_id' => $show->id, 'season' => 6, 'number' => 2, 'type' => 'regular']),
    ]);

    $listener = new SendSubscriptionNotification;
    $listener->handle(new SubscriptionTriggered($request, $show, $episodes));

    Notification::assertSentOnDemand(SubscriptionMediaNotification::class);
});

it('does not send notification when slack is disabled', function () {
    Notification::fake();
    Log::spy();
    config(['services.slack.enabled' => false]);

    $request = Request::factory()->create();
    $movie = Movie::factory()->create();

    $listener = new SendSubscriptionNotification;
    $listener->handle(new SubscriptionTriggered($request, $movie));

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

    $listener = new SendSubscriptionNotification;
    $listener->handle(new SubscriptionTriggered($request, $movie));

    Notification::assertNothingSent();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($message) => $message === 'Slack notification skipped: channel not configured')
        ->once();
});
