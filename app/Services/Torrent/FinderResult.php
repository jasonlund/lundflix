<?php

declare(strict_types=1);

namespace App\Services\Torrent;

final class FinderResult
{
    /**
     * @param  array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}|null  $match
     */
    public function __construct(
        public readonly ?array $match,
        public readonly bool $oversizeCandidatesExisted,
    ) {}

    public static function none(bool $oversize = false): self
    {
        return new self(null, $oversize);
    }

    /**
     * @param  array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}  $match
     */
    public static function hit(array $match): self
    {
        return new self($match, false);
    }
}
