<?php

namespace App\Events;

use App\Models\Movie;
use App\Models\Request;
use App\Models\Show;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class SubscriptionTriggered implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * @param  Collection<int, \App\Models\Episode>|null  $episodes
     */
    public function __construct(
        public ?Request $request,
        public Movie|Show $media,
        public ?Collection $episodes = null,
    ) {}
}
