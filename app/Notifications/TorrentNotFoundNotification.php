<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Episode;
use App\Models\Movie;
use App\Models\RequestItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;

class TorrentNotFoundNotification extends Notification
{
    use Queueable;

    /**
     * @param  list<RequestItem>  $items
     */
    public function __construct(public array $items) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        $count = count($this->items);
        $label = $count === 1 ? 'item' : 'items';
        $list = implode("\n", array_map(fn (RequestItem $i): string => '• '.self::formatItem($i), $this->items));

        return (new SlackMessage)
            ->text("{$count} {$label} had no matching torrent")
            ->sectionBlock(function (SectionBlock $block) use ($count, $label, $list): void {
                $block->text("🔍 *Could not find torrents*\n\n{$count} {$label} had no matching torrent on IPT:\n{$list}")->markdown();
            });
    }

    public static function formatItem(RequestItem $item): string
    {
        $target = $item->requestable;

        if ($target instanceof Movie) {
            $year = $target->year ? " ({$target->year})" : '';

            return "Movie — {$target->title}{$year}";
        }

        if ($target instanceof Episode) {
            $showName = $target->show->name ?? 'Unknown show';
            $code = strtoupper($target->code);

            return "Episode — {$showName} {$code}";
        }

        return 'Unknown item';
    }
}
