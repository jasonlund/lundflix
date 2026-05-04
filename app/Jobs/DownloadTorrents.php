<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SlackNotificationType;
use App\Exceptions\IptorrentsRateLimitExceededException;
use App\Notifications\TorrentIgnoredNotification;
use App\Notifications\TorrentRejectedNotification;
use App\Services\IptorrentsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

class DownloadTorrents implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 5;

    public int $maxExceptions = 3;

    private const POLL_INTERVAL_SECONDS = 5;

    private const POLL_MAX_SECONDS = 60;

    /**
     * @param  list<array{torrent_id: int, filename: string}>  $torrents
     */
    public function __construct(
        public array $torrents,
    ) {
        $this->onQueue('torrents');
    }

    public function handle(IptorrentsService $ipt): void
    {
        /** @var list<string> $rejected */
        $rejected = [];
        /** @var list<string> $ignored */
        $ignored = [];

        foreach ($this->torrents as $index => $torrent) {
            try {
                $this->processTorrent($ipt, $torrent, $rejected, $ignored);
            } catch (IptorrentsRateLimitExceededException) {
                $this->torrents = array_slice($this->torrents, $index);
                $this->sendNotifications($rejected, $ignored);
                $this->release(60);

                return;
            } catch (\Throwable $e) {
                Log::error('Torrent processing failed', [
                    'torrent_id' => $torrent['torrent_id'],
                    'filename' => $torrent['filename'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->sendNotifications($rejected, $ignored);
    }

    /**
     * @param  array{torrent_id: int, filename: string}  $torrent
     * @param  list<string>  $rejected
     * @param  list<string>  $ignored
     */
    private function processTorrent(
        IptorrentsService $ipt,
        array $torrent,
        array &$rejected,
        array &$ignored,
    ): void {
        $localPath = $ipt->download($torrent['torrent_id'], $torrent['filename']);

        try {
            $contents = file_get_contents($localPath);

            if ($contents === false) {
                throw new \RuntimeException("Failed to read local file: {$localPath}");
            }

            Storage::disk('torrent')->put($torrent['filename'], $contents);
        } catch (\Throwable $e) {
            @unlink($localPath);

            throw $e;
        }

        @unlink($localPath);

        $this->pollForResult($torrent, $rejected, $ignored);
    }

    /**
     * @param  array{torrent_id: int, filename: string}  $torrent
     * @param  list<string>  $rejected
     * @param  list<string>  $ignored
     */
    private function pollForResult(array $torrent, array &$rejected, array &$ignored): void
    {
        $filename = $torrent['filename'];
        $disk = Storage::disk('torrent');

        for ($elapsed = 0; $elapsed <= self::POLL_MAX_SECONDS; $elapsed += self::POLL_INTERVAL_SECONDS) {
            if ($elapsed > 0) {
                Sleep::for(self::POLL_INTERVAL_SECONDS)->seconds();
            }

            if ($disk->exists("{$filename}.invalid")) {
                $rejected[] = $filename;

                Log::info('Torrent rejected by client', [
                    'torrent_id' => $torrent['torrent_id'],
                    'filename' => $filename,
                ]);

                return;
            }

            if (! $disk->exists($filename)) {
                return;
            }
        }

        $ignored[] = $filename;

        Log::info('Torrent not picked up by client', [
            'torrent_id' => $torrent['torrent_id'],
            'filename' => $filename,
        ]);
    }

    /**
     * @param  list<string>  $rejected
     * @param  list<string>  $ignored
     */
    private function sendNotifications(array $rejected, array $ignored): void
    {
        if (! config('services.slack.enabled')) {
            return;
        }

        if ($rejected !== []) {
            $channel = SlackNotificationType::TorrentRejected->channel();

            if ($channel) {
                Log::info('Sending torrent rejected notification', [
                    'filenames' => $rejected,
                    'channel' => $channel,
                ]);

                Notification::route('slack', $channel)
                    ->notify(new TorrentRejectedNotification($rejected));
            }
        }

        if ($ignored !== []) {
            $channel = SlackNotificationType::TorrentIgnored->channel();

            if ($channel) {
                Log::info('Sending torrent ignored notification', [
                    'filenames' => $ignored,
                    'channel' => $channel,
                ]);

                Notification::route('slack', $channel)
                    ->notify(new TorrentIgnoredNotification($ignored));
            }
        }
    }
}
