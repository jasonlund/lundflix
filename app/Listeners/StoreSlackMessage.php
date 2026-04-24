<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\SlackNotificationType;
use App\Models\SlackMessage;
use Illuminate\Http\Client\Response;
use Illuminate\Notifications\Events\NotificationSent;

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

        SlackMessage::create([
            'channel' => $event->response->json('channel'),
            'message_ts' => $event->response->json('ts'),
            'type' => $type,
            'content' => $event->response->json('message.blocks.0.text.text', ''),
            'sent_at' => now(),
        ]);
    }
}
