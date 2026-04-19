<?php

namespace App\Events;

use App\Enums\ReleaseQuality;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Request;
use App\Models\Show;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class MediaAvailable implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * @param  Collection<int, Episode>|null  $episodes
     */
    public function __construct(
        public ?Request $request,
        public Movie|Show $media,
        public ?Collection $episodes = null,
        public ?ReleaseQuality $quality = null,
    ) {}
}
