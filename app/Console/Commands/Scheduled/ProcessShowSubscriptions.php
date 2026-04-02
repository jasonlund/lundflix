<?php

namespace App\Console\Commands\Scheduled;

use App\Actions\Request\CreateRequest;
use App\Actions\Request\CreateRequestItems;
use App\Enums\MediaType;
use App\Events\RequestSubmitted;
use App\Models\Episode;
use App\Models\Show;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessShowSubscriptions extends Command
{
    protected $signature = 'process:show-subscriptions';

    protected $description = 'Create requests for users subscribed to shows with newly aired episodes';

    public function __construct(
        private readonly CreateRequest $createRequest,
        private readonly CreateRequestItems $createRequestItems,
    ) {
        parent::__construct();
    }

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
                ->whereDate('airdate', '>=', $windowStartDate)
                ->whereDate('airdate', '<=', $nowDate),
            ])
            ->get()
            ->keyBy('id');

        $processed = 0;

        foreach ($subscriptions as $subscription) {
            /** @var Show $show */
            $show = $shows->get($subscription->subscribable_id);

            $newEpisodes = $show->episodes
                ->filter(function (Episode $episode) use ($windowStart, $now): bool {
                    if (! $episode->airdate) {
                        return false;
                    }

                    $airtime = $episode->airtime ?? '00:00';
                    $airdatetime = Carbon::parse($episode->airdate->format('Y-m-d').' '.$airtime); // @phpstan-ignore method.nonObject (casted to Carbon)

                    return $airdatetime->greaterThan($windowStart) && $airdatetime->lessThanOrEqualTo($now);
                })
                ->sortBy([['season', 'asc'], ['number', 'asc']])
                ->values();

            if ($newEpisodes->isEmpty()) {
                continue;
            }

            $request = $this->createRequest->create($subscription->user);
            $this->createRequestItems->create(
                $request,
                $newEpisodes->map(fn (Episode $episode) => ['type' => MediaType::EPISODE, 'id' => $episode->id])->all(),
            );

            RequestSubmitted::dispatch($request);

            $processed++;
        }

        $this->info("Processed {$processed} show subscription(s).");

        return Command::SUCCESS;
    }
}
