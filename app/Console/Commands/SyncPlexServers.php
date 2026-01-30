<?php

namespace App\Console\Commands;

use App\Models\PlexMediaServer;
use App\Services\PlexService;
use Illuminate\Console\Command;

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

        foreach ($servers as $server) {
            PlexMediaServer::updateOrCreate(
                ['client_identifier' => $server['clientIdentifier']],
                [
                    'name' => $server['name'],
                    'access_token' => $server['accessToken'],
                    'owned' => $server['owned'] ?? false,
                    'is_online' => $server['presence'] ?? false,
                    'connections' => $server['connections'] ?? [],
                    'uri' => $this->selectBestConnection($server['connections'] ?? []),
                    'last_seen_at' => now(),
                ]
            );
        }

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
