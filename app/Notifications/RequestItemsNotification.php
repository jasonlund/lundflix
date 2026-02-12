<?php

namespace App\Notifications;

use App\Models\Request;
use App\Services\RequestItemGrouper;
use App\Support\RequestItemFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;

class RequestItemsNotification extends Notification
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
        /** @var \Illuminate\Support\Collection<int, \App\Models\Movie|\App\Models\Episode> $requestables */
        $requestables = $this->request->items
            ->map(fn ($item) => $item->requestable) // @phpstan-ignore property.notFound
            ->filter();

        $grouped = app(RequestItemGrouper::class)->group($requestables);

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
                    $parts[] = RequestItemFormatter::formatSeason($seasonData['season']);
                } else {
                    foreach ($seasonData['runs'] as $run) {
                        $parts[] = RequestItemFormatter::formatRun($run);
                    }
                }
            }

            $lines[] = $showGroup['show']->name.' '.implode(', ', $parts);
        }

        return implode("\n", $lines);
    }
}
