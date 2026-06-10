<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\RequestItemStatus;
use App\Exceptions\IptorrentsAuthException;
use App\Exceptions\IptorrentsRateLimitExceededException;
use App\Models\Episode;
use App\Models\Request;
use App\Services\TorrentFulfillmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessRequest implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Request $request,
    ) {}

    public function handle(TorrentFulfillmentService $fulfillment): void
    {
        $items = $this->request->items()
            ->where('status', RequestItemStatus::Pending)
            ->with(['requestable' => fn ($morphTo) => $morphTo->morphWith([
                Episode::class => ['show'],
            ])])
            ->get();

        $media = $items->map(fn ($item) => $item->requestable)->filter()->values();

        try {
            $result = $fulfillment->fulfill($media);
        } catch (IptorrentsRateLimitExceededException) {
            return;
        } catch (IptorrentsAuthException $e) {
            Log::warning($e->getMessage());

            return;
        }

        if ($result->downloads !== []) {
            DownloadTorrents::dispatch($result->downloads);
        }
    }
}
