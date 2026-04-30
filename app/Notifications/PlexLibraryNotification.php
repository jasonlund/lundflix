<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Support\PlexLibraryFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Support\Collection;

class PlexLibraryNotification extends Notification
{
    use Queueable;

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     */
    public function __construct(
        public ?string $serverName,
        public Collection $items,
        public string $clientIdentifier = '',
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        $items = (new PlexLibraryFormatter($this->clientIdentifier ?: null))->format($this->items);
        $heading = $this->serverName
            ? "☑️ Added to the library of {$this->serverName}"
            : '☑️ Added to the library';

        return (new SlackMessage)
            ->text("{$heading}\n\n{$items}")
            ->unfurlLinks(false)
            ->unfurlMedia(false)
            ->sectionBlock(function (SectionBlock $block) use ($heading, $items): void {
                $block->text("*{$heading}*\n\n{$items}")->markdown();
            });
    }
}
