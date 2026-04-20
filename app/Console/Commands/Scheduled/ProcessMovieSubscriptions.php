<?php

declare(strict_types=1);

namespace App\Console\Commands\Scheduled;

use App\Enums\MovieStatus;
use App\Events\SubscriptionTriggered;
use App\Models\Movie;
use App\Models\Subscription;
use Illuminate\Console\Command;

class ProcessMovieSubscriptions extends Command
{
    protected $signature = 'process:movie-subscriptions';

    protected $description = 'Notify subscribers when subscribed movies release digitally';

    public function handle(): int
    {
        $today = today();

        $subscriptions = Subscription::query()
            ->active()
            ->forMovies()
            ->with(['subscribable', 'user'])
            ->get();

        $processed = 0;
        $notified = [];

        foreach ($subscriptions as $subscription) {
            /** @var Movie $movie */
            $movie = $subscription->subscribable;

            if ($movie->status !== MovieStatus::Released) {
                continue;
            }

            if (! $movie->digital_release_date?->isSameDay($today)) {
                continue;
            }

            if (! isset($notified[$movie->id])) {
                SubscriptionTriggered::dispatch(null, $movie);
                $notified[$movie->id] = true;
                $processed++;
            }

            $subscription->markFulfilled();
        }

        $this->info("Processed {$processed} movie subscription(s).");

        return Command::SUCCESS;
    }
}
