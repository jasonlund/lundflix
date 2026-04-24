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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function fakeSlackResponse(
    bool $ok = true,
    string $channel = 'C0123456789',
    string $messageTs = '1234567890.123456',
    string $content = "*📝 New Request*\n\nInception (2010)",
): Response {
    $response = Mockery::mock(Response::class);
    $response->shouldReceive('json')->with('ok')->andReturn($ok);

    if ($ok) {
        $response->shouldReceive('json')->with('channel')->andReturn($channel);
        $response->shouldReceive('json')->with('ts')->andReturn($messageTs);
        $response->shouldReceive('json')->with('message.blocks.0.text.text', '')->andReturn($content);
    }

    return $response;
}

it('stores a slack message from a NotificationSent event', function () {
    $request = Request::factory()->create();
    $notification = new RequestItemsNotification($request);

    $event = new NotificationSent(
        new AnonymousNotifiable,
        $notification,
        'slack',
        fakeSlackResponse(),
    );

    (new StoreSlackMessage)->handle($event);

    expect(SlackMessage::count())->toBe(1);

    $message = SlackMessage::first();
    expect($message->channel)->toBe('C0123456789')
        ->and($message->message_ts)->toBe('1234567890.123456')
        ->and($message->type)->toBe(SlackNotificationType::RequestItems)
        ->and($message->content)->toBe("*📝 New Request*\n\nInception (2010)");
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

it('ignores failed slack responses', function () {
    $request = Request::factory()->create();

    $event = new NotificationSent(
        new AnonymousNotifiable,
        new RequestItemsNotification($request),
        'slack',
        fakeSlackResponse(ok: false),
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

it('updates an existing slack message instead of duplicating it', function () {
    $message = SlackMessage::factory()->create([
        'channel' => 'C0123456789',
        'message_ts' => '1234567890.123456',
        'type' => SlackNotificationType::RequestItems,
        'content' => 'Original content',
    ]);

    $request = Request::factory()->create();

    $event = new NotificationSent(
        new AnonymousNotifiable,
        new RequestItemsNotification($request),
        'slack',
        fakeSlackResponse(content: "*📝 New Request*\n\nUpdated content"),
    );

    (new StoreSlackMessage)->handle($event);

    expect(SlackMessage::count())->toBe(1);
    expect($message->fresh()->content)->toBe("*📝 New Request*\n\nUpdated content");
});

it('logs and swallows storage failures after slack succeeds', function () {
    Log::shouldReceive('error')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'Failed to store sent Slack message.'
                && $context['notification'] === RequestItemsNotification::class
                && $context['channel'] === 'C0123456789'
                && $context['message_ts'] === '1234567890.123456'
                && filled($context['error']);
        });

    Schema::drop('slack_messages');

    $request = Request::factory()->create();

    $event = new NotificationSent(
        new AnonymousNotifiable,
        new RequestItemsNotification($request),
        'slack',
        fakeSlackResponse(),
    );

    (new StoreSlackMessage)->handle($event);

    expect(Schema::hasTable('slack_messages'))->toBeFalse();
});
