<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\RequestItemStatus;
use App\Exceptions\IptorrentsAuthException;
use App\Exceptions\IptorrentsRateLimitExceededException;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Request;
use App\Models\RequestItem;
use App\Services\IptorrentsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessRequest implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Request $request,
    ) {}

    public function handle(IptorrentsService $ipt): void
    {
        $items = $this->request->items()
            ->where('status', RequestItemStatus::Pending)
            ->with(['requestable' => fn ($morphTo) => $morphTo->morphWith([
                Episode::class => ['show'],
            ])])
            ->get();

        /** @var list<array{torrent_id: int, filename: string}> $torrentDownloads */
        $torrentDownloads = [];

        foreach ($items as $item) {
            try {
                $result = $this->search($ipt, $item);
            } catch (IptorrentsRateLimitExceededException) {
                break;
            } catch (IptorrentsAuthException $e) {
                Log::warning($e->getMessage());

                break;
            } catch (\Throwable $e) {
                Log::warning('IPTorrents request availability check failed', [
                    'request_item_id' => $item->id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if ($result === null) {
                continue;
            }

            $torrentDownloads[] = [
                'torrent_id' => $result['torrent_id'],
                'filename' => basename((string) parse_url($result['download_url'], PHP_URL_PATH)),
            ];
        }

        if ($torrentDownloads !== []) {
            DownloadTorrents::dispatch($torrentDownloads);
        }
    }

    /**
     * @return array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}|null
     */
    private function search(IptorrentsService $ipt, RequestItem $item): ?array
    {
        $requestable = $item->requestable;

        return match (true) {
            $requestable instanceof Movie => $ipt->searchMovie($requestable),
            $requestable instanceof Episode => $ipt->searchEpisode($requestable),
            default => null,
        };
    }
}
