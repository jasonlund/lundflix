<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\SlackNotificationType;
use App\Models\SlackMessage;
use Illuminate\Http\Client\Response;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Log;

class StoreSlackMessage
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'slack') {
            return;
        }

        $type = SlackNotificationType::tryFromNotification($event->notification::class);

        if (! $type) {
            return;
        }

        if (! $event->response instanceof Response) {
            return;
        }

        if ($event->response->json('ok') !== true) {
            return;
        }

        $channel = $event->response->json('channel');
        $messageTs = $event->response->json('ts');

        if (! is_string($channel) || ! is_string($messageTs)) {
            return;
        }

        try {
            SlackMessage::query()->updateOrCreate(
                [
                    'channel' => $channel,
                    'message_ts' => $messageTs,
                ],
                [
                    'type' => $type,
                    'content' => $event->response->json('message.blocks.0.text.text', ''),
                    'sent_at' => now(),
                ],
            );
        } catch (\Throwable $exception) {
            Log::error('Failed to store sent Slack message.', [
                'notification' => $event->notification::class,
                'channel' => $channel,
                'message_ts' => $messageTs,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
