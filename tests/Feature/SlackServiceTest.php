<?php

use App\Models\SlackMessage;
use App\Services\SlackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.slack.notifications.bot_user_oauth_token' => 'xoxb-test-token']);
});

it('updates a slack message via the API', function () {
    Http::fake([
        'slack.com/api/chat.update' => Http::response(['ok' => true]),
    ]);

    $message = SlackMessage::factory()->create([
        'channel' => 'C0123456789',
        'message_ts' => '1234567890.123456',
        'content' => 'old content',
    ]);

    $newContent = '*📝 New Request*\n\nUpdated content';

    (new SlackService)->updateMessage($message, $newContent);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://slack.com/api/chat.update'
            && $request['channel'] === 'C0123456789'
            && $request['ts'] === '1234567890.123456'
            && $request['blocks'][0]['type'] === 'section'
            && $request['blocks'][0]['text']['text'] === '*📝 New Request*\n\nUpdated content'
            && $request->hasHeader('Authorization', 'Bearer xoxb-test-token');
    });

    expect($message->fresh()->content)->toBe($newContent);
});

it('throws on update failure', function () {
    Http::fake([
        'slack.com/api/chat.update' => Http::response(['ok' => false, 'error' => 'channel_not_found']),
    ]);

    $message = SlackMessage::factory()->create();

    (new SlackService)->updateMessage($message, 'new content');
})->throws(RuntimeException::class, 'Slack API error: channel_not_found');

it('deletes a slack message via the API', function () {
    Http::fake([
        'slack.com/api/chat.delete' => Http::response(['ok' => true]),
    ]);

    $message = SlackMessage::factory()->create([
        'channel' => 'C0123456789',
        'message_ts' => '1234567890.123456',
    ]);

    (new SlackService)->deleteMessage($message);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://slack.com/api/chat.delete'
            && $request['channel'] === 'C0123456789'
            && $request['ts'] === '1234567890.123456';
    });

    expect(SlackMessage::find($message->id))->toBeNull();
});

it('throws on delete failure', function () {
    Http::fake([
        'slack.com/api/chat.delete' => Http::response(['ok' => false, 'error' => 'message_not_found']),
    ]);

    $message = SlackMessage::factory()->create();

    (new SlackService)->deleteMessage($message);
})->throws(RuntimeException::class, 'Slack API error: message_not_found');
