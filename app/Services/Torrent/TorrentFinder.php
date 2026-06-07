<?php

declare(strict_types=1);

namespace App\Services\Torrent;

interface TorrentFinder
{
    public function supports(TorrentRequest $request): bool;

    public function findDetailed(TorrentRequest $request): FinderResult;
}
