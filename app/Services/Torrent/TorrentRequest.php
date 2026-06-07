<?php

declare(strict_types=1);

namespace App\Services\Torrent;

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use InvalidArgumentException;

final readonly class TorrentRequest
{
    public function __construct(
        public Kind $kind,
        public Movie|Episode|SeasonPackTarget $target,
        public int $maxBytes,
    ) {
        $valid = match ($kind) {
            Kind::Movie => $target instanceof Movie,
            Kind::Episode => $target instanceof Episode,
            Kind::SeasonPack => $target instanceof SeasonPackTarget,
        };

        if (! $valid) {
            throw new InvalidArgumentException("Invalid target for kind {$kind->value}.");
        }
    }

    public function movie(): Movie
    {
        if (! $this->target instanceof Movie) {
            throw new InvalidArgumentException('Not a movie request.');
        }

        return $this->target;
    }

    public function episode(): Episode
    {
        if (! $this->target instanceof Episode) {
            throw new InvalidArgumentException('Not an episode request.');
        }

        return $this->target;
    }

    public function show(): Show
    {
        if (! $this->target instanceof SeasonPackTarget) {
            throw new InvalidArgumentException('Not a season pack request.');
        }

        return $this->target->show;
    }

    public function season(): int
    {
        if (! $this->target instanceof SeasonPackTarget) {
            throw new InvalidArgumentException('Not a season pack request.');
        }

        return $this->target->season;
    }
}
