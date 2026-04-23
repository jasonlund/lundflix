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

    public function toSlack(object $notifiable): SlackMessage
    {
        $message = (new SlackMessage)
            ->text($this->formatItems())
            ->headerBlock('📤 Request Processed')
            ->sectionBlock(function (SectionBlock $block): void {
                $block->text(__('lundbergh.notification.request_processed'));
            });

        foreach ($this->groupedByStatus() as [$label, $lines]) {
            $message->sectionBlock(function (SectionBlock $block) use ($label, $lines): void {
                $block->text("*{$label}:*\n".implode("\n", $lines))->markdown();
            });
        }

        return $message;
    }

    private function formatItems(): string
    {
        $sections = [];

        foreach ($this->groupedByStatus() as [$label, $lines]) {
            $sections[] = "*{$label}:*\n".implode("\n", $lines);
        }

        return implode("\n\n", $sections);
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
