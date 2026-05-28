<?php

declare(strict_types=1);

namespace App\Services\Torrent;

final class ResolveResult
{
    /**
     * @param  array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}|null  $match
     */
    public function __construct(
        public readonly ?array $match,
        public readonly bool $oversizeCandidatesExisted,
    ) {}
}
