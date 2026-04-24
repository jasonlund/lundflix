<?php

use App\Enums\SlackNotificationType;
use App\Listeners\StoreSlackMessage;
use App\Models\Movie;
use App\Models\Request;
use App\Models\SlackMessage;
use App\Notifications\MediaAvailableNotification;
use App\Notifications\RequestItemsNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationSent;

uses(RefreshDatabase::class);

it('stores a slack message from a NotificationSent event', function () {
    $response = Mockery::mock(Response::class);
    $response->shouldReceive('json')->with('channel')->andReturn('C0123456789');
    $response->shouldReceive('json')->with('ts')->andReturn('1234567890.123456');
    $response->shouldReceive('json')->with('message.blocks.0.text.text', '')->andReturn('*📝 New Request*\n\nInception (2010)');

    $request = Request::factory()->create();
    $notification = new RequestItemsNotification($request);

    $event = new NotificationSent(
        new AnonymousNotifiable,
        $notification,
        'slack',
        $response,
    );

    (new StoreSlackMessage)->handle($event);

    expect(SlackMessage::count())->toBe(1);

    $message = SlackMessage::first();
    expect($message->channel)->toBe('C0123456789')
        ->and($message->message_ts)->toBe('1234567890.123456')
        ->and($message->type)->toBe(SlackNotificationType::RequestItems)
        ->and($message->content)->toBe('*📝 New Request*\n\nInception (2010)');
});

it('ignores non-slack channels', function () {
    $request = Request::factory()->create();

    $event = new NotificationSent(
        new AnonymousNotifiable,
        new RequestItemsNotification($request),
        'mail',
        null,
    );

    (new StoreSlackMessage)->handle($event);

    expect(SlackMessage::count())->toBe(0);
});

it('ignores unknown notification types', function () {
    $notification = new class extends \Illuminate\Notifications\Notification {};

    $event = new NotificationSent(
        new AnonymousNotifiable,
        $notification,
        'slack',
        Mockery::mock(Response::class),
    );

    (new StoreSlackMessage)->handle($event);

    expect(SlackMessage::count())->toBe(0);
});

it('ignores events without an HTTP response', function () {
    $movie = Movie::factory()->create();

    $event = new NotificationSent(
        new AnonymousNotifiable,
        new MediaAvailableNotification($movie),
        'slack',
        'not-a-response',
    );

    (new StoreSlackMessage)->handle($event);

    expect(SlackMessage::count())->toBe(0);
});
