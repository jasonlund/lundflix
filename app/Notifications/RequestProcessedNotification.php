<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\RequestItemStatus;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Request;
use App\Models\Show;
use App\Services\CartService;
use App\Support\Formatters;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Support\Collection;

class RequestProcessedNotification extends Notification
{
    use Queueable;

    public function __construct(public Request $request) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    private const SLACK_SECTION_LIMIT = 3000;

    public function toSlack(object $notifiable): SlackMessage
    {
        $heading = '*📤 Request Processed*';
        $blocks = $this->buildBlocks($heading);
        $plainText = implode("\n\n", array_map(
            fn (array $pair) => "*{$pair[0]}:*\n".implode("\n", $pair[1]),
            $this->groupedByStatus(),
        ));

        $message = (new SlackMessage)->text($plainText);

        foreach ($blocks as $block) {
            $message->sectionBlock(function (SectionBlock $sectionBlock) use ($block): void {
                $sectionBlock->text($block)->markdown();
            });
        }

        return $message;
    }

    /**
     * @return list<string>
     */
    private function buildBlocks(string $heading): array
    {
        $blocks = [];
        $current = $heading;

        foreach ($this->groupedByStatus() as [$label, $lines]) {
            $labelLine = "\n\n*{$label}:*";

            if (mb_strlen($current.$labelLine."\n".implode("\n", $lines)) <= self::SLACK_SECTION_LIMIT) {
                $current .= $labelLine."\n".implode("\n", $lines);

                continue;
            }

            if (mb_strlen($current.$labelLine) <= self::SLACK_SECTION_LIMIT) {
                $current .= $labelLine;
            } else {
                $blocks[] = $current;
                $current = "*{$label}:*";
            }

            foreach ($lines as $line) {
                if (mb_strlen($current."\n".$line) > self::SLACK_SECTION_LIMIT) {
                    $blocks[] = $current;
                    $current = $line;
                } else {
                    $current .= "\n".$line;
                }
            }
        }

        $blocks[] = $current;

        return $blocks;
    }

    /**
     * @return list<array{0: string, 1: array<int, string>}>
     */
    private function groupedByStatus(): array
    {
        $statusGroups = [
            [RequestItemStatus::Fulfilled, 'Fulfilled'],
            [RequestItemStatus::Rejected, 'Rejected'],
            [RequestItemStatus::NotFound, 'Not Found'],
        ];

        $result = [];

        foreach ($statusGroups as [$status, $label]) {
            /** @var Collection<int, Movie|Episode> $requestables */
            $requestables = $this->request->items
                ->where('status', $status)
                ->map(fn ($item) => $item->requestable) // @phpstan-ignore property.notFound
                ->filter();

            if ($requestables->isEmpty()) {
                continue;
            }

            $grouped = app(CartService::class)->groupItems($requestables);
            $result[] = [$label, $this->formatGroupedItems($grouped)];
        }

        return $result;
    }

    /**
     * @param  array{movies: Collection<int, Movie>, shows: array<int, array{show: Show, seasons: array<int, array{season: int, is_full: bool, runs: array<int, Collection<int, Episode>>, episodes: Collection<int, Episode>}>}>}  $grouped
     * @return array<int, string>
     */
    private function formatGroupedItems(array $grouped): array
    {
        $lines = [];

        foreach ($grouped['movies'] as $movie) {
            $line = $movie->title;
            if ($movie->year) {
                $line .= " ({$movie->year})";
            }
            $lines[] = $line;
        }

        foreach ($grouped['shows'] as $showGroup) {
            $parts = [];

            foreach ($showGroup['seasons'] as $seasonData) {
                if ($seasonData['is_full']) {
                    $parts[] = Formatters::formatSeason($seasonData['season']);
                } else {
                    foreach ($seasonData['runs'] as $run) {
                        $parts[] = Formatters::formatRun($run);
                    }
                }
            }

            $lines[] = $showGroup['show']->name.' '.implode(', ', $parts);
        }

        return $lines;
    }
}
