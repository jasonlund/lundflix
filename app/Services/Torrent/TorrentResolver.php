<?php

declare(strict_types=1);

namespace App\Services\Torrent;

use App\Exceptions\IptorrentsAuthException;
use App\Exceptions\IptorrentsRateLimitExceededException;
use App\Services\Torrent\Support\VerifiedResultPicker;
use Illuminate\Support\Facades\Log;
use Throwable;

class TorrentResolver
{
    /**
     * @param  list<TorrentFinder>  $finders
     */
    public function __construct(private readonly array $finders) {}

    /**
     * @return array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}|null
     */
    public function resolve(TorrentRequest $request): ?array
    {
        return $this->resolveDetailed($request)->match;
    }

    public function resolveDetailed(TorrentRequest $request): FinderResult
    {
        $oversizeSeen = false;

        foreach ($this->finders as $finder) {
            if (! $finder->supports($request)) {
                continue;
            }

            try {
                $result = $finder->findDetailed($request);
            } catch (IptorrentsRateLimitExceededException|IptorrentsAuthException $e) {
                throw $e;
            } catch (Throwable $e) {
                Log::warning('Torrent finder threw exception', [
                    'finder' => $finder::class,
                    'kind' => $request->kind->value,
                    'message' => $e->getMessage(),
                ]);

                continue;
            }

            if ($result->oversizeCandidatesExisted) {
                $oversizeSeen = true;
            }

            if ($result->match === null) {
                continue;
            }

            if (! VerifiedResultPicker::fits($result->match['size'], $request->maxBytes)) {
                $oversizeSeen = true;

                continue;
            }

            return new FinderResult($result->match, $oversizeSeen);
        }

        return new FinderResult(null, $oversizeSeen);
    }
}
