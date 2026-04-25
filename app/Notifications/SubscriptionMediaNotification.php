<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use App\Services\CartService;
use App\Support\Formatters;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Support\Collection;

class SubscriptionMediaNotification extends Notification
{
    use Queueable;

    /**
     * @param  Collection<int, Episode>|null  $episodes
     */
    public function __construct(
        public Movie|Show $media,
        public ?Collection $episodes = null,
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
        if ($this->media instanceof Movie) {
            return $this->movieMessage();
        }

        return $this->showMessage();
    }

    private function movieMessage(): SlackMessage
    {
        /** @var Movie $movie */
        $movie = $this->media;

        $title = $movie->title;
        if ($movie->year) {
            $title .= " ({$movie->year})";
        }

        return (new SlackMessage)
            ->text($title)
            ->sectionBlock(function (SectionBlock $block) use ($title): void {
                $block->text("*🎬 New Release*\n\n{$title}")->markdown();
            });
    }

    private function showMessage(): SlackMessage
    {
        /** @var Show $show */
        $show = $this->media;
        $episodes = $this->episodes ?? collect();

        $episodeCount = $episodes->count();
        $header = $episodeCount === 1 ? '📺 New Episode' : '📺 New Episodes';

        $grouped = app(CartService::class)->groupItems($episodes); // @phpstan-ignore argument.type
        $parts = [];

        foreach ($grouped['shows'] as $showGroup) {
            foreach ($showGroup['seasons'] as $seasonData) {
                if ($seasonData['is_full']) {
                    $parts[] = Formatters::formatSeason($seasonData['season']);
                } else {
                    foreach ($seasonData['runs'] as $run) {
                        $parts[] = Formatters::formatRun($run);
                    }
                }
            }
        }

        $detail = $show->name.' '.implode(', ', $parts);

        return (new SlackMessage)
            ->text($detail)
            ->sectionBlock(function (SectionBlock $block) use ($header, $detail): void {
                $block->text("*{$header}*\n\n{$detail}")->markdown();
            });
    }
}
