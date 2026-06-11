<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\RequestItemStatus;
use App\Exceptions\IptorrentsRateLimitExceededException;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Request;
use App\Services\TorrentFulfillmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

class ProcessRequest implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 5;

    public int $backoff = 60;

    public function __construct(
        public Request $request,
    ) {
        $this->onQueue('torrents');
    }

    public function handle(TorrentFulfillmentService $fulfillment): void
    {
        $items = $this->request->items()
            ->where('status', RequestItemStatus::Pending)
            ->with(['requestable' => fn ($morphTo) => $morphTo->morphWith([
                Episode::class => ['show'],
            ])])
            ->get();

        /** @var Collection<int, Movie|Episode> $media */
        $media = $items->map(fn ($item) => $item->requestable)->filter()->values();

        try {
            $result = $fulfillment->fulfill($media);
        } catch (IptorrentsRateLimitExceededException) {
            $this->release($this->backoff);

            return;
        }

        if ($result->downloads !== []) {
            DownloadTorrents::dispatch($result->downloads);
        }
    }
}
