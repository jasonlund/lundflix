<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\RequestSubmitted;
use App\Models\Episode;
use App\Services\Torrent\ApplyDownloadPlan;
use App\Services\Torrent\RequestDownloadPlanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchRequestDownloads implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly RequestDownloadPlanner $planner,
        private readonly ApplyDownloadPlan $applyDownloadPlan,
    ) {}

    public function handle(RequestSubmitted $event): void
    {
        $request = $event->request->load(['items.requestable' => function ($morphTo): void {
            $morphTo->morphWith([
                Episode::class => ['show.episodes'],
            ]);
        }]);

        if ($request->items->isEmpty()) {
            return;
        }

        $plan = $this->planner->plan($request);

        $this->applyDownloadPlan->apply($request, $plan);
    }
}
