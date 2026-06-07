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
     * @param  list<array{item: RequestItem, maxBytes: int}>  $items
     */
    public function __construct(
        public array $items,
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

        $message = (new SlackMessage)
            ->text("{$count} {$label} matched but exceeded size cap")
            ->sectionBlock(function (SectionBlock $block): void {
                $block->text('📦 *Matches found but all exceed size cap*')->markdown();
            });

        foreach ($this->groupByCap() as $maxBytes => $items) {
            $cap = TorrentSize::format($maxBytes);
            $groupCount = count($items);
            $groupLabel = $groupCount === 1 ? 'item' : 'items';
            $list = implode("\n", array_map(
                fn (RequestItem $i): string => '• '.TorrentNotFoundNotification::formatItem($i),
                $items,
            ));

            $message->sectionBlock(function (SectionBlock $block) use ($groupCount, $groupLabel, $cap, $list): void {
                $block->text("{$groupCount} {$groupLabel} had matches but the smallest was over {$cap}:\n{$list}")->markdown();
            });
        }

        return $message;
    }

    /**
     * @return array<int, list<RequestItem>>
     */
    private function groupByCap(): array
    {
        /** @var array<int, list<RequestItem>> $groups */
        $groups = [];

        foreach ($this->items as $entry) {
            $groups[$entry['maxBytes']][] = $entry['item'];
        }

        ksort($groups);

        return $groups;
    }
}
