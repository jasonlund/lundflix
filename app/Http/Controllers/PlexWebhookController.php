<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ProcessPlexWebhookBatch;
use App\Support\PlexWebhookBatchStore;
use App\Support\PlexWebhookNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Compatibility;

class PlexWebhookController extends Controller
{
    public function __invoke(Request $request, string $token, PlexWebhookNormalizer $normalizer, PlexWebhookBatchStore $batchStore): JsonResponse
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
            Log::warning('Plex webhook rejected: missing addedAt', [
                'title' => $metadata['title'] ?? 'Unknown',
            ]);

            return response()->json(['status' => 'ok']);
        }

        $addedAtDate = Carbon::createFromTimestamp((int) $addedAt);

        if ($addedAtDate->isBefore(now()->subMinutes($maxAgeMinutes))) {
            Log::warning('Plex webhook rejected: addedAt too old', [
                'title' => $metadata['title'] ?? 'Unknown',
                'addedAt' => $addedAtDate->toIso8601String(),
                'maxAgeMinutes' => $maxAgeMinutes,
                ...$this->traceContext(),
            ]);

            return response()->json(['status' => 'ok']);
        }

        $normalized = $normalizer->normalize($payload);
        $warningContext = $this->contextFor($normalized);

        if ($normalized['warnings'] !== []) {
            Log::warning('Plex webhook identifiers degraded', [
                ...$warningContext,
                'warnings' => $normalized['warnings'],
            ]);
        }

        Log::info('Plex webhook accepted', $warningContext);

        $batch = $batchStore->upsert($normalized);

        Log::info('Plex webhook batch updated', [
            ...$warningContext,
            'version' => $batch['version'],
            'item_count' => count($batch['items']),
            'flush_at' => Carbon::createFromTimestamp((int) $batch['flush_at'])->toIso8601String(),
            'hard_deadline_at' => Carbon::createFromTimestamp((int) $batch['hard_deadline_at'])->toIso8601String(),
        ]);

        ProcessPlexWebhookBatch::dispatch(
            serverUuid: $normalized['server_uuid'],
            groupKey: $normalized['group_key'],
            version: (int) $batch['version'],
        )
            ->onQueue((string) config('services.plex.webhook_queue', 'plex-webhooks'))
            ->delay(Carbon::createFromTimestamp((int) $batch['flush_at']));

        return response()->json(['status' => 'ok']);
    }

    /**
     * @param  array{
     *     server_uuid: string,
     *     group_key: string,
     *     item: array<string, mixed>
     * }  $normalized
     * @return array<string, mixed>
     */
    private function contextFor(array $normalized): array
    {
        return [
            'server_uuid' => $normalized['server_uuid'],
            'group_key' => $normalized['group_key'],
            'rating_key' => $normalized['item']['rating_key'],
            ...$this->traceContext(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function traceContext(): array
    {
        $traceId = class_exists(Compatibility::class)
            ? Compatibility::getTraceIdFromContext()
            : null;

        return $traceId ? ['trace_id' => $traceId] : [];
    }
}
