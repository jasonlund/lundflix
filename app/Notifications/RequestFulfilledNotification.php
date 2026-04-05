<?php

namespace App\Notifications;

use App\Enums\RequestItemStatus;
use App\Models\Request;
use App\Services\CartService;
use App\Support\Formatters;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;

class RequestFulfilledNotification extends Notification
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
        return (new SlackMessage)->text($this->formatItems());
    }

    private function formatItems(): string
    {
        $sections = [];

        $statusGroups = [
            [RequestItemStatus::Fulfilled, 'Fulfilled'],
            [RequestItemStatus::Rejected, 'Rejected'],
            [RequestItemStatus::NotFound, 'Not Found'],
        ];

        foreach ($statusGroups as [$status, $label]) {
            /** @var \Illuminate\Support\Collection<int, \App\Models\Movie|\App\Models\Episode> $requestables */
            $requestables = $this->request->items
                ->where('status', $status)
                ->map(fn ($item) => $item->requestable) // @phpstan-ignore property.notFound
                ->filter();

            if ($requestables->isEmpty()) {
                continue;
            }

            $grouped = app(CartService::class)->groupItems($requestables);
            $lines = $this->formatGroupedItems($grouped);

            $sections[] = "*{$label}:*\n".implode("\n", $lines);
        }

        return implode("\n\n", $sections);
    }

    /**
     * @param  array{movies: \Illuminate\Support\Collection<int, \App\Models\Movie>, shows: array<int, array{show: \App\Models\Show, seasons: array<int, array{season: int, is_full: bool, runs: array<int, \Illuminate\Support\Collection<int, \App\Models\Episode>>, episodes: \Illuminate\Support\Collection<int, \App\Models\Episode>}>}>}  $grouped
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
