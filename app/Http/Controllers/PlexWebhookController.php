<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ProcessPlexWebhookBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PlexWebhookController extends Controller
{
    public function __invoke(Request $request, string $token): JsonResponse
    {
        if (! hash_equals((string) config('services.plex.webhook_secret'), $token)) {
            abort(403);
        }

        $payload = json_decode((string) $request->input('payload'), true);

        if (! is_array($payload)) {
            return response()->json(['status' => 'ok']);
        }

        if (($payload['event'] ?? null) !== 'library.new') {
            return response()->json(['status' => 'ok']);
        }

        $metadata = $payload['Metadata'] ?? [];
        $mediaType = $metadata['type'] ?? null;

        if (! in_array($mediaType, ['movie', 'episode'], true)) {
            return response()->json(['status' => 'ok']);
        }

        $addedAt = $metadata['addedAt'] ?? null;
        $maxAgeMinutes = (int) config('services.plex.webhook_added_at_max_age_minutes', 15);

        if (! is_numeric($addedAt) || $addedAt <= 0) {
            Log::debug('Plex webhook rejected: missing addedAt', [
                'title' => $metadata['title'] ?? 'Unknown',
            ]);

            return response()->json(['status' => 'ok']);
        }

        $addedAtDate = Carbon::createFromTimestamp((int) $addedAt);

        if ($addedAtDate->isBefore(now()->subMinutes($maxAgeMinutes))) {
            Log::debug('Plex webhook rejected: addedAt too old', [
                'title' => $metadata['title'] ?? 'Unknown',
                'addedAt' => $addedAtDate->toIso8601String(),
                'maxAgeMinutes' => $maxAgeMinutes,
            ]);

            return response()->json(['status' => 'ok']);
        }

        $serverUuid = $payload['Server']['uuid'] ?? 'unknown';
        $serverName = $payload['Server']['title'] ?? null;

        $item = [
            'media_type' => $mediaType,
            'title' => $metadata['title'] ?? 'Unknown',
            'year' => $mediaType === 'movie' ? ($metadata['year'] ?? null) : null,
            'show_title' => $mediaType === 'episode' ? ($metadata['grandparentTitle'] ?? null) : null,
            'season' => $mediaType === 'episode' ? ($metadata['parentIndex'] ?? null) : null,
            'episode_number' => $mediaType === 'episode' ? ($metadata['index'] ?? null) : null,
        ];

        $cacheKey = "plex-webhook:{$serverUuid}";

        Cache::lock("{$cacheKey}:lock", 10)->block(5, function () use ($cacheKey, $serverName, $item): void {
            $batch = Cache::get($cacheKey, ['server_name' => null, 'items' => [], 'last_received_at' => null]);
            $batch['server_name'] = $serverName ?? $batch['server_name'];

            $isDuplicate = collect($batch['items'])->contains(function (array $existing) use ($item): bool {
                if ($item['media_type'] === 'movie') {
                    return $existing['media_type'] === 'movie'
                        && $existing['title'] === $item['title']
                        && $existing['year'] === $item['year'];
                }

                return $existing['media_type'] === 'episode'
                    && $existing['show_title'] === $item['show_title']
                    && $existing['season'] === $item['season']
                    && $existing['episode_number'] === $item['episode_number'];
            });

            if (! $isDuplicate) {
                $batch['items'][] = $item;
            }

            $batch['last_received_at'] = now()->timestamp;
            Cache::put($cacheKey, $batch, now()->addHours(4));
        });

        $debounceSeconds = (int) config('services.plex.webhook_debounce_seconds');

        ProcessPlexWebhookBatch::dispatch($serverUuid)
            ->delay(now()->addSeconds($debounceSeconds));

        return response()->json(['status' => 'ok']);
    }
}
