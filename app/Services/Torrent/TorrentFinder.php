<?php

declare(strict_types=1);

namespace App\Services\Torrent;

interface TorrentFinder
{
    public function supports(TorrentRequest $request): bool;

    /**
     * @return array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}|null
     */
    public function find(TorrentRequest $request): ?array;
}
