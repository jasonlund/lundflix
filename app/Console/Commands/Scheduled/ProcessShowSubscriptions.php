<?php

namespace App\Console\Commands\Scheduled;

use App\Actions\Request\CreateRequest;
use App\Actions\Request\CreateRequestItems;
use App\Enums\MediaType;
use App\Events\RequestSubmitted;
use App\Models\Episode;
use App\Models\Show;
use App\Models\Subscription;
use App\Support\AirDateTime;
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
                ->whereDate('airdate', '>=', Carbon::parse($windowStartDate)->subDay()->toDateString())
                ->whereDate('airdate', '<=', Carbon::parse($nowDate)->addDay()->toDateString()),
            ])
            ->get()
            ->keyBy('id');

        $processed = 0;

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
