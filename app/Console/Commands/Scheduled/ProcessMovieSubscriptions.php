<?php

namespace App\Console\Commands\Scheduled;

use App\Actions\Request\CreateRequest;
use App\Actions\Request\CreateRequestItems;
use App\Enums\MediaType;
use App\Enums\MovieStatus;
use App\Events\RequestSubmitted;
use App\Models\Movie;
use App\Models\Subscription;
use Illuminate\Console\Command;

class ProcessMovieSubscriptions extends Command
{
    protected $signature = 'process:movie-subscriptions';

    protected $description = 'Create requests for users subscribed to movies releasing today';

    public function __construct(
        private readonly CreateRequest $createRequest,
        private readonly CreateRequestItems $createRequestItems,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $today = today();

        $subscriptions = Subscription::query()
            ->where('subscribable_type', Movie::class)
            ->with(['subscribable', 'user'])
            ->get();

        $processed = 0;

        foreach ($subscriptions as $subscription) {
            /** @var Movie $movie */
            $movie = $subscription->subscribable;

            if ($movie->status !== MovieStatus::Released) {
                continue;
            }

            if (! $movie->digital_release_date?->isSameDay($today)) {
                continue;
            }

            $request = $this->createRequest->create($subscription->user);
            $this->createRequestItems->create($request, [
                ['type' => MediaType::MOVIE, 'id' => $movie->id],
            ]);

            RequestSubmitted::dispatch($request);

            $processed++;
        }

        $this->info("Processed {$processed} movie subscription(s).");

        return Command::SUCCESS;
    }
}
