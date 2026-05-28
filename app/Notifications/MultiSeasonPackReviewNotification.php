<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Support\TorrentSize;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;

class MultiSeasonPackReviewNotification extends Notification
{
    use Queueable;

    /**
     * @param  list<array{show: \App\Models\Show, season: int, pack: array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}, requestItems: list<\App\Models\RequestItem>}>  $entries
     */
    public function __construct(public array $entries) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        $count = count($this->entries);
        $label = $count === 1 ? 'show' : 'shows';
        $lines = [];

        foreach ($this->entries as $entry) {
            $show = $entry['show'];
            $season = $entry['season'];
            $pack = $entry['pack'];

            $sizeBytes = $this->parseSize($pack['size']);
            $sizeDisplay = $sizeBytes === null ? $pack['size'] : TorrentSize::format($sizeBytes);

            $lines[] = "• {$show->name} — Season {$season}";
            $lines[] = "  Pack: \"{$pack['name']}\" ({$sizeDisplay})";
            $lines[] = "  {$pack['download_url']}";
        }

        $list = implode("\n", $lines);

        return (new SlackMessage)
            ->text("Multi-season pack detected for {$count} {$label}")
            ->sectionBlock(function (SectionBlock $block) use ($count, $label, $list): void {
                $block->text("🧑‍⚖️ *Multi-season pack detected — manual review*\n\n{$count} {$label} had no single-season pack but a multi-season pack covers them:\n{$list}")->markdown();
            });
    }

    private function parseSize(string $size): ?int
    {
        try {
            return TorrentSize::parse($size);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
