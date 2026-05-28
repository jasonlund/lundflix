<?php

declare(strict_types=1);

namespace App\Services\Torrent;

interface DetailedTorrentFinder extends TorrentFinder
{
    public function findDetailed(TorrentRequest $request): FinderResult;
}
