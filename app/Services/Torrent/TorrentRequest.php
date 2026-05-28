<?php

declare(strict_types=1);

namespace App\Services\Torrent;

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use InvalidArgumentException;

final class TorrentRequest
{
    /**
     * @param  Movie|Episode|array<string, mixed>  $target
     */
    public function __construct(
        public readonly Kind $kind,
        public readonly Movie|Episode|array $target,
        public readonly int $maxBytes,
    ) {
        $valid = match ($kind) {
            Kind::Movie => $target instanceof Movie,
            Kind::Episode => $target instanceof Episode,
            Kind::SeasonPack => is_array($target)
                && ($target['show'] ?? null) instanceof Show
                && is_int($target['season'] ?? null),
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
        if (! is_array($this->target) || ! (($this->target['show'] ?? null) instanceof Show)) {
            throw new InvalidArgumentException('Not a season pack request.');
        }

        return $this->target['show'];
    }

    public function season(): int
    {
        if (! is_array($this->target) || ! is_int($this->target['season'] ?? null)) {
            throw new InvalidArgumentException('Not a season pack request.');
        }

        return $this->target['season'];
    }
}
