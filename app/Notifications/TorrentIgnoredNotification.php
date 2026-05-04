<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;

class TorrentIgnoredNotification extends Notification
{
    use Queueable;

    /**
     * @param  list<string>  $filenames
     */
    public function __construct(
        public array $filenames,
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
        $count = count($this->filenames);
        $label = $count === 1 ? 'torrent was' : 'torrents were';
        $list = implode("\n", array_map(fn (string $f): string => "• {$f}", $this->filenames));

        return (new SlackMessage)
            ->text("{$count} {$label} not picked up")
            ->sectionBlock(function (SectionBlock $block) use ($count, $label, $list): void {
                $title = $count === 1 ? 'Watcher Ignored Torrent' : 'Watcher Ignored Torrents';
                $block->text("‼️ *{$title}*\n\n{$count} {$label} not picked up by the torrent watcher:\n{$list}")->markdown();
            });
    }
}
