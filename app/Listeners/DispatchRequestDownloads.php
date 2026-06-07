<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\RequestSubmitted;
use App\Exceptions\IptorrentsAuthException;
use App\Exceptions\IptorrentsRateLimitExceededException;
use App\Models\Episode;
use App\Services\Torrent\ApplyDownloadPlan;
use App\Services\Torrent\RequestDownloadPlanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DispatchRequestDownloads implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly RequestDownloadPlanner $planner,
        private readonly ApplyDownloadPlan $applyDownloadPlan,
    ) {}

    public function handle(RequestSubmitted $event): void
    {
        $request = $event->request->load('items');

        if ($request->items->isEmpty()) {
            return;
        }

        $hasEpisodes = $request->items
            ->contains('requestable_type', (new Episode)->getMorphClass());

        $request->load(['items.requestable' => function ($morphTo) use ($hasEpisodes): void {
            if ($hasEpisodes) {
                $morphTo->morphWith([
                    Episode::class => ['show.episodes'],
                ]);
            }
        }]);

        try {
            $plan = $this->planner->plan($request);
        } catch (IptorrentsRateLimitExceededException) {
            $this->release(60);

            return;
        } catch (IptorrentsAuthException $e) {
            Log::warning($e->getMessage());
            $this->fail($e);

            return;
        }

        $this->applyDownloadPlan->apply($request, $plan);
    }
}
