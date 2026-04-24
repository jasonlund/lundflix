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
        $formatter = new PlexLibraryFormatter;
        $items = $formatter->format($this->items);

        return (new SlackMessage)
            ->text($items)
            ->sectionBlock(function (SectionBlock $block) use ($items): void {
                $block->text("*☑️ Added to library*\n\n{$items}")->markdown();
            });
    }
}
