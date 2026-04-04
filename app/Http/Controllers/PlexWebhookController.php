<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPlexWebhookBatch;
use App\Models\PlexWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $serverUuid = $payload['Server']['uuid'] ?? 'unknown';
        $serverName = $payload['Server']['title'] ?? null;

        $isDuplicate = PlexWebhookEvent::query()
            ->unprocessed()
            ->forServer($serverUuid)
            ->where('media_type', $mediaType)
            ->where('title', $metadata['title'] ?? '')
            ->when($mediaType === 'episode', function ($query) use ($metadata) {
                $query->where('show_title', $metadata['grandparentTitle'] ?? '')
                    ->where('season', $metadata['parentIndex'] ?? null)
                    ->where('episode_number', $metadata['index'] ?? null);
            })
            ->where('created_at', '>=', now()->subSeconds(60))
            ->exists();

        if ($isDuplicate) {
            return response()->json(['status' => 'ok']);
        }

        PlexWebhookEvent::create([
            'server_uuid' => $serverUuid,
            'server_name' => $serverName,
            'media_type' => $mediaType,
            'title' => $metadata['title'] ?? 'Unknown',
            'year' => $mediaType === 'movie' ? ($metadata['year'] ?? null) : null,
            'show_title' => $mediaType === 'episode' ? ($metadata['grandparentTitle'] ?? null) : null,
            'season' => $mediaType === 'episode' ? ($metadata['parentIndex'] ?? null) : null,
            'episode_number' => $mediaType === 'episode' ? ($metadata['index'] ?? null) : null,
            'payload' => $payload,
        ]);

        $debounceSeconds = config('services.plex.webhook_debounce_seconds');

        ProcessPlexWebhookBatch::dispatch($serverUuid)
            ->delay(now()->addSeconds($debounceSeconds));

        return response()->json(['status' => 'ok']);
    }
}
