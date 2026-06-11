<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class MediaFoundInLibrary implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * @param  Collection<int, Episode>|null  $episodes
     */
    public function __construct(
        public Movie|Show $media,
        public ?Collection $episodes = null,
    ) {}
}
