<?php

declare(strict_types=1);

namespace App\Services\Torrent;

use App\Enums\RequestItemStatus;
use App\Enums\SlackNotificationType;
use App\Jobs\DownloadTorrents;
use App\Models\Request;
use App\Models\RequestItem;
use App\Notifications\MultiSeasonPackReviewNotification;
use App\Notifications\TorrentNotFoundNotification;
use App\Notifications\TorrentOversizeNotification;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ApplyDownloadPlan
{
    /**
     * @param  array<int, true>  $dispatchedTorrentIds  Tracks torrent_ids already dispatched across plans in one run.
     */
    public function apply(Request $request, PlanResult $plan, array &$dispatchedTorrentIds = []): void
    {
        $downloads = array_values(array_filter(
            $plan->downloads,
            function (array $download) use (&$dispatchedTorrentIds): bool {
                if (isset($dispatchedTorrentIds[$download['torrent_id']])) {
                    return false;
                }

                $dispatchedTorrentIds[$download['torrent_id']] = true;

                return true;
            },
        ));

        if ($downloads !== []) {
            DownloadTorrents::dispatch($downloads);
        }

        $this->markNotFound($plan->notFound);
        $this->markNotFound(array_map(
            fn (array $entry): RequestItem => $entry['item'],
            $plan->oversize,
        ));

        $this->fireNotifications($plan);
    }

    /**
     * @param  list<RequestItem>  $items
     */
    private function markNotFound(array $items): void
    {
        if ($items === []) {
            return;
        }

        $now = now();

        foreach ($items as $item) {
            $item->forceFill([
                'status' => RequestItemStatus::NotFound,
                'actioned_at' => $now,
            ])->save();
        }
    }

    private function fireNotifications(PlanResult $plan): void
    {
        if (! config('services.slack.enabled')) {
            return;
        }

        if ($plan->notFound !== []) {
            $this->sendIfChannel(
                SlackNotificationType::TorrentNotFound,
                fn (): TorrentNotFoundNotification => new TorrentNotFoundNotification($plan->notFound),
            );
        }

        if ($plan->oversize !== []) {
            $this->sendIfChannel(
                SlackNotificationType::TorrentOversize,
                fn (): TorrentOversizeNotification => new TorrentOversizeNotification($plan->oversize),
            );
        }

        if ($plan->multiSeasonReview !== []) {
            $this->sendIfChannel(
                SlackNotificationType::MultiSeasonPackReview,
                fn (): MultiSeasonPackReviewNotification => new MultiSeasonPackReviewNotification(
                    $plan->multiSeasonReview,
                ),
            );
        }
    }

    private function sendIfChannel(SlackNotificationType $type, Closure $factory): void
    {
        $channel = $type->channel();

        if (! $channel) {
            Log::warning('Slack notification skipped: channel not configured', [
                'type' => $type->value,
            ]);

            return;
        }

        Notification::route('slack', $channel)->notify($factory());
    }
}
