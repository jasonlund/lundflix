<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SlackMessage;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SlackService
{
    public function updateMessage(SlackMessage $message, string $content): void
    {
        $blocks = [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $content,
                ],
            ],
        ];

        $response = $this->client()->post('https://slack.com/api/chat.update', [
            'channel' => $message->channel,
            'ts' => $message->message_ts,
            'blocks' => $blocks,
            'text' => strip_tags($content),
        ]);

        if ($response->json('ok') !== true) {
            throw new RuntimeException('Slack API error: '.$response->json('error', 'unknown'));
        }

        $message->update(['content' => $content]);
    }

    public function deleteMessage(SlackMessage $message): void
    {
        $response = $this->client()->post('https://slack.com/api/chat.delete', [
            'channel' => $message->channel,
            'ts' => $message->message_ts,
        ]);

        if ($response->json('ok') !== true) {
            throw new RuntimeException('Slack API error: '.$response->json('error', 'unknown'));
        }

        $message->delete();
    }

    private function client(): PendingRequest
    {
        return Http::asJson()
            ->withToken(config('services.slack.notifications.bot_user_oauth_token'));
    }
}
