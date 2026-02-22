<?php

namespace App\Console\Commands;

use App\Models\PlexMediaServer;
use App\Services\PlexService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncPlexServers extends Command
{
    protected $signature = 'plex:sync-servers';

    protected $description = 'Sync Plex server status from the admin user';

    public function handle(PlexService $plex): int
    {
        $adminToken = config('services.plex.seed_token');
        if (! $adminToken) {
            $this->error('No admin seed token configured.');

            return Command::FAILURE;
        }

        $resources = $plex->getUserResources($adminToken);
        $servers = $resources->filter(fn (array $r): bool => ($r['provides'] ?? '') === 'server');

        $adminThumb = $plex->getUserInfo($adminToken)['thumb'];
        $friendThumbs = $plex->getFriends($adminToken)->pluck('thumb', 'id');

        foreach ($servers as $server) {
            $owned = $server['owned'] ?? false;

            PlexMediaServer::updateOrCreate(
                ['client_identifier' => $server['clientIdentifier']],
                [
                    'name' => $server['name'],
                    'access_token' => $server['accessToken'],
                    'owned' => $owned,
                    'is_online' => $server['presence'] ?? false,
                    'connections' => $server['connections'] ?? [],
                    'uri' => $this->selectBestConnection($server['connections'] ?? []),
                    'source_title' => $server['sourceTitle'] ?? null,
                    'owner_thumb' => $owned ? $adminThumb : $friendThumbs->get($server['ownerId'] ?? null),
                    'owner_id' => $server['ownerId'] ?? null,
                    'product_version' => $server['productVersion'] ?? null,
                    'platform' => $server['platform'] ?? null,
                    'platform_version' => $server['platformVersion'] ?? null,
                    'plex_last_seen_at' => $server['lastSeenAt'] ?? null,
                    'last_seen_at' => now(),
                ]
            );
        }

        Cache::forget('plex:visible-servers');

        $this->info("Synced {$servers->count()} servers.");

        return Command::SUCCESS;
    }

    /**
     * @param  array<int, array{uri: string, local: bool}>  $connections
     */
    private function selectBestConnection(array $connections): ?string
    {
        $nonLocal = collect($connections)->filter(fn (array $c): bool => ! $c['local']);
        $direct = $nonLocal->first(fn (array $c): bool => ! str_contains($c['uri'], 'plex.direct'));

        return $direct['uri'] ?? $nonLocal->first()['uri'] ?? null;
    }
}
