<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\RequestItem;
use App\Support\TorrentSize;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;

class TorrentOversizeNotification extends Notification
{
    use Queueable;

    /**
     * @param  list<RequestItem>  $items
     */
    public function __construct(
        public array $items,
        public int $maxBytes,
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
        $count = count($this->items);
        $label = $count === 1 ? 'item' : 'items';
        $cap = TorrentSize::format($this->maxBytes);
        $list = implode("\n", array_map(
            fn (RequestItem $i): string => '• '.TorrentNotFoundNotification::formatItem($i),
            $this->items,
        ));

        return (new SlackMessage)
            ->text("{$count} {$label} matched but exceeded size cap")
            ->sectionBlock(function (SectionBlock $block) use ($count, $label, $cap, $list): void {
                $block->text("📦 *Matches found but all exceed size cap*\n\n{$count} {$label} had matches but the smallest was over {$cap}:\n{$list}")->markdown();
            });
    }
}
