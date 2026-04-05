<?php

namespace App\Notifications;

use App\Models\PlexWebhookEvent;
use App\Support\PlexWebhookFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Support\Collection;

class PlexLibraryNotification extends Notification
{
    use Queueable;

    /**
     * @param  Collection<int, PlexWebhookEvent>  $events
     */
    public function __construct(public Collection $events) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        $formatter = new PlexWebhookFormatter;

        return (new SlackMessage)->text($formatter->format($this->events));
    }
}
