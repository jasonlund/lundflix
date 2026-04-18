<?php

namespace App\Console\Commands\Scheduled;

use App\Events\SubscriptionTriggered;
use App\Models\Episode;
use App\Models\Show;
use App\Models\Subscription;
use App\Support\AirDateTime;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessShowSubscriptions extends Command
{
    protected $signature = 'process:show-subscriptions';

    protected $description = 'Notify subscribers when new episodes of subscribed shows air';

    public function handle(): int
    {
        $now = now();
        $windowStart = $now->copy()->subMinutes(15);

        $windowStartDate = $windowStart->toDateString();
        $nowDate = $now->toDateString();

        $subscriptions = Subscription::query()
            ->where('subscribable_type', Show::class)
            ->with('user')
            ->get();

        $showIds = $subscriptions->pluck('subscribable_id');

        $shows = Show::query()
            ->whereIn('id', $showIds)
            ->with(['episodes' => fn ($q) => $q
                ->whereDate('airdate', '>=', Carbon::parse($windowStartDate)->subDay()->toDateString())
                ->whereDate('airdate', '<=', Carbon::parse($nowDate)->addDay()->toDateString()),
            ])
            ->get()
            ->keyBy('id');

        $processed = 0;
        $notified = [];

        foreach ($subscriptions as $subscription) {
            /** @var Show $show */
            $show = $shows->get($subscription->subscribable_id);

            $newEpisodes = $show->episodes
                ->filter(function (Episode $episode) use ($windowStart, $now, $show): bool {
                    if (! $episode->airdate) {
                        return false;
                    }

                    $airdatetime = AirDateTime::resolve(
                        $episode->airdate->format('Y-m-d'), // @phpstan-ignore method.nonObject (casted to Carbon)
                        $episode->airtime,
                        $show->web_channel, // @phpstan-ignore argument.type (casted to array)
                        $show->network, // @phpstan-ignore argument.type (casted to array)
                    );

                    return $airdatetime->greaterThan($windowStart) && $airdatetime->lessThanOrEqualTo($now);
                })
                ->sortBy([['season', 'asc'], ['number', 'asc']])
                ->values();

            if ($newEpisodes->isEmpty()) {
                continue;
            }

            if (isset($notified[$show->id])) {
                continue;
            }

            SubscriptionTriggered::dispatch(null, $show, $newEpisodes);
            $notified[$show->id] = true;

            $processed++;
        }

        $this->info("Processed {$processed} show subscription(s).");

        return Command::SUCCESS;
    }
}
