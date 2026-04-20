<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class PlexWebhookBatchStore
{
    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     *
     * @throws LockTimeoutException
     */
    public function withBatchLock(
        string $serverUuid,
        string $groupKey,
        Closure $callback,
        int $seconds = 10,
        int $waitSeconds = 5,
    ): mixed {
        return $this->lockProvider()
            ->lock($this->lockKey($serverUuid, $groupKey), $seconds)
            ->block($waitSeconds, $callback);
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn|null
     */
    public function withProcessingLock(
        string $serverUuid,
        string $groupKey,
        Closure $callback,
        int $seconds = 300,
    ): mixed {
        return $this->lockProvider()
            ->lock($this->processingLockKey($serverUuid, $groupKey), $seconds)
            ->get($callback);
    }

    /**
     * @param  array{
     *     server_uuid: string,
     *     server_name: string|null,
     *     group_key: string,
     *     group_type: string,
     *     item_key: string,
     *     item: array<string, mixed>
     * }  $normalized
     * @return array<string, mixed>
     */
    public function upsert(array $normalized): array
    {
        return $this->withBatchLock($normalized['server_uuid'], $normalized['group_key'], function () use ($normalized): array {
            $existing = $this->get($normalized['server_uuid'], $normalized['group_key']);
            $item = $normalized['item'];
            $items = $existing['items'] ?? [];
            $items[$normalized['item_key']] = $item;

            $batch = [
                'server_uuid' => $normalized['server_uuid'],
                'server_name' => $normalized['server_name'] ?? ($existing['server_name'] ?? null),
                'group_key' => $normalized['group_key'],
                'group_type' => $normalized['group_type'],
                'first_received_at' => $existing['first_received_at'] ?? $item['received_at'],
                'last_received_at' => $item['received_at'],
                'hard_deadline_at' => $existing['hard_deadline_at'] ?? ($item['received_at'] + $this->maxBatchSeconds()),
                'version' => (int) ($existing['version'] ?? 0) + 1,
                'items' => $items,
            ];

            $batch['flush_at'] = min(
                $batch['last_received_at'] + $this->debounceSeconds(),
                $batch['hard_deadline_at']
            );

            $this->put($normalized['server_uuid'], $normalized['group_key'], $batch);

            return $batch;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $serverUuid, string $groupKey): ?array
    {
        $batch = $this->repository()->get($this->batchKey($serverUuid, $groupKey));

        return is_array($batch) ? $batch : null;
    }

    public function forget(string $serverUuid, string $groupKey): void
    {
        $this->repository()->forget($this->batchKey($serverUuid, $groupKey));
    }

    /**
     * @param  list<string>  $processedItemKeys
     * @return array<string, mixed>|null
     */
    public function finalizeProcessedItems(string $serverUuid, string $groupKey, int $version, array $processedItemKeys): ?array
    {
        return $this->withBatchLock($serverUuid, $groupKey, function () use ($serverUuid, $groupKey, $version, $processedItemKeys): ?array {
            $batch = $this->get($serverUuid, $groupKey);

            if (! $batch) {
                return null;
            }

            foreach ($processedItemKeys as $processedItemKey) {
                unset($batch['items'][$processedItemKey]);
            }

            if (empty($batch['items']) || (int) ($batch['version'] ?? 0) === $version) {
                $this->forget($serverUuid, $groupKey);

                return null;
            }

            $rebuiltBatch = $this->rebuild($batch);

            $this->put($serverUuid, $groupKey, $rebuiltBatch);

            return $rebuiltBatch;
        });
    }

    /**
     * @param  array<string, mixed>  $batch
     * @return array<string, mixed>
     */
    private function rebuild(array $batch): array
    {
        $items = array_values($batch['items'] ?? []);
        $receivedAt = array_column($items, 'received_at');
        sort($receivedAt);

        $firstReceivedAt = (int) ($receivedAt[0] ?? now()->timestamp);
        $lastReceivedAt = (int) ($receivedAt[count($receivedAt) - 1] ?? $firstReceivedAt);

        $batch['first_received_at'] = $firstReceivedAt;
        $batch['last_received_at'] = $lastReceivedAt;
        $batch['flush_at'] = min($lastReceivedAt + $this->debounceSeconds(), (int) $batch['hard_deadline_at']);

        return $batch;
    }

    /**
     * @param  array<string, mixed>  $batch
     */
    private function put(string $serverUuid, string $groupKey, array $batch): void
    {
        $this->repository()->put(
            $this->batchKey($serverUuid, $groupKey),
            $batch,
            $this->maxBatchSeconds() + $this->debounceSeconds() + 300
        );
    }

    private function batchKey(string $serverUuid, string $groupKey): string
    {
        return "plex:webhook:batch:{$serverUuid}:".sha1($groupKey);
    }

    private function lockKey(string $serverUuid, string $groupKey): string
    {
        return "plex:webhook:lock:{$serverUuid}:".sha1($groupKey);
    }

    private function processingLockKey(string $serverUuid, string $groupKey): string
    {
        return "plex:webhook:processing:{$serverUuid}:".sha1($groupKey);
    }

    private function debounceSeconds(): int
    {
        return (int) config('services.plex.webhook_debounce_seconds', 30);
    }

    private function maxBatchSeconds(): int
    {
        return (int) config('services.plex.webhook_max_batch_seconds', 3600);
    }

    private function lockProvider(): LockProvider
    {
        /** @var LockProvider */
        return $this->repository()->getStore();
    }

    private function repository(): Repository
    {
        /** @var Repository */
        return Cache::store((string) config('services.plex.webhook_cache_store', 'redis'));
    }
}
