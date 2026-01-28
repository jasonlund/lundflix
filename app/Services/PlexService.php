<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class PlexService
{
    private const BASE_URL = 'https://clients.plex.tv/api/v2';

    private string $clientIdentifier;

    private string $productName;

    private string $serverIdentifier;

    public function __construct()
    {
        $this->clientIdentifier = config('services.plex.client_identifier');
        $this->productName = config('services.plex.product_name');
        $this->serverIdentifier = config('services.plex.server_identifier');
    }

    /**
     * Create a PIN for Plex authentication.
     *
     * @return array{id: int, code: string}
     */
    public function createPin(): array
    {
        $response = $this->client()
            ->post(self::BASE_URL.'/pins?strong=true');

        return [
            'id' => $response->json('id'),
            'code' => $response->json('code'),
        ];
    }

    /**
     * Get the auth token from a claimed PIN.
     * Returns null if PIN not yet claimed.
     */
    public function getTokenFromPin(int $pinId): ?string
    {
        $response = $this->client()
            ->get(self::BASE_URL."/pins/{$pinId}");

        return $response->json('authToken');
    }

    /**
     * Generate the Plex auth URL for user redirect.
     */
    public function getAuthUrl(string $code, string $forwardUrl): string
    {
        $params = http_build_query([
            'clientID' => $this->clientIdentifier,
            'code' => $code,
            'forwardUrl' => $forwardUrl,
            'context[device][product]' => $this->productName,
        ]);

        return "https://app.plex.tv/auth#?{$params}";
    }

    /**
     * Get the authenticated user's info from Plex.
     *
     * @return array{id: int, uuid: string, username: string, email: string, thumb: string|null}
     */
    public function getUserInfo(string $token): array
    {
        $response = $this->client($token)
            ->get('https://plex.tv/api/v2/user');

        return [
            'id' => $response->json('id'),
            'uuid' => $response->json('uuid'),
            'username' => $response->json('username'),
            'email' => $response->json('email'),
            'thumb' => $response->json('thumb'),
        ];
    }

    /**
     * Get all resources (servers, players) the user has access to.
     */
    public function getUserResources(string $token): Collection
    {
        $response = $this->client($token)
            ->get(self::BASE_URL.'/resources', [
                'includeHttps' => 1,
                'includeRelay' => 1,
                'includeIPv6' => 1,
            ]);

        return collect($response->json());
    }

    /**
     * Check if the user has access to our configured Plex server.
     */
    public function hasServerAccess(string $token): bool
    {
        $resources = $this->getUserResources($token);

        return $resources->contains(function (array $resource) {
            return $resource['clientIdentifier'] === $this->serverIdentifier
                && $resource['provides'] === 'server';
        });
    }

    /**
     * Get all online Plex servers the user has access to.
     */
    public function getOnlineServers(string $token): Collection
    {
        return $this->getUserResources($token)
            ->filter(fn (array $r): bool => ($r['provides'] ?? '') === 'server' && ($r['presence'] ?? false) === true)
            ->map(fn (array $r): array => [
                'name' => $r['name'],
                'clientIdentifier' => $r['clientIdentifier'],
                'accessToken' => $r['accessToken'],
                'owned' => $r['owned'],
                'uri' => $this->selectBestConnection($r['connections'] ?? []),
            ])
            ->filter(fn (array $s): bool => $s['uri'] !== null)
            ->values();
    }

    /**
     * Select the best connection URI from a server's connections.
     * Prefers non-local, non-relay connections that don't use plex.direct.
     */
    private function selectBestConnection(array $connections): ?string
    {
        $nonLocal = collect($connections)->filter(fn (array $c): bool => ! $c['local']);

        // Prefer direct connections (not plex.direct relay)
        $direct = $nonLocal->first(fn (array $c): bool => ! str_contains($c['uri'], 'plex.direct'));
        $fallback = $nonLocal->first();

        return $direct['uri'] ?? $fallback['uri'] ?? null;
    }

    /**
     * Resolve an external ID (IMDB/TVDB/TMDB) to a Plex GUID.
     *
     * @param  string  $externalGuid  e.g., "imdb://tt1375666" or "tvdb://396390"
     * @param  int  $type  1=movie, 2=show
     */
    public function resolvePlexGuid(string $token, string $externalGuid, int $type): ?string
    {
        $response = $this->client($token)
            ->get('https://metadata.provider.plex.tv/library/metadata/matches', [
                'type' => $type,
                'guid' => $externalGuid,
            ]);

        return $response->json('MediaContainer.Metadata.0.guid');
    }

    /**
     * Search for media across all online servers by external ID.
     *
     * @param  string  $externalGuid  e.g., "imdb://tt1375666" or "tvdb://396390"
     * @param  int  $type  1=movie, 2=show
     */
    public function searchByExternalId(string $token, string $externalGuid, int $type): Collection
    {
        $plexGuid = $this->resolvePlexGuid($token, $externalGuid, $type);

        if (! $plexGuid) {
            return collect();
        }

        $servers = $this->getOnlineServers($token);

        if ($servers->isEmpty()) {
            return collect();
        }

        // Query all servers concurrently
        $responses = Http::pool(fn (Pool $pool) => $servers->map(
            fn (array $server) => $pool
                ->as($server['clientIdentifier'])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Plex-Client-Identifier' => $this->clientIdentifier,
                    'X-Plex-Token' => $server['accessToken'],
                ])
                ->timeout(10)
                ->get($server['uri'].'/library/all', ['guid' => $plexGuid])
        )->all());

        return $servers
            ->map(function (array $server) use ($responses): ?array {
                $response = $responses[$server['clientIdentifier']] ?? null;

                if (! $response || $response instanceof \Throwable || $response->failed()) {
                    return null;
                }

                $match = $response->json('MediaContainer.Metadata.0');

                return $match ? [...$server, 'match' => $match] : null;
            })
            ->filter()
            ->values();
    }

    /**
     * Search for a TV show and return servers with their available episodes.
     *
     * @param  string  $externalGuid  e.g., "imdb://tt31987295" or "tvdb://396390"
     */
    public function searchShowWithEpisodes(string $token, string $externalGuid): Collection
    {
        $serversWithShow = $this->searchByExternalId($token, $externalGuid, 2);

        if ($serversWithShow->isEmpty()) {
            return collect();
        }

        // Fetch all episodes concurrently
        $responses = Http::pool(fn (Pool $pool) => $serversWithShow->map(
            fn (array $server) => $pool
                ->as($server['clientIdentifier'])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Plex-Client-Identifier' => $this->clientIdentifier,
                    'X-Plex-Token' => $server['accessToken'],
                ])
                ->timeout(10)
                ->get($server['uri']."/library/metadata/{$server['match']['ratingKey']}/allLeaves")
        )->all());

        return $serversWithShow->map(function (array $server) use ($responses): array {
            $response = $responses[$server['clientIdentifier']] ?? null;

            $episodes = ($response && ! $response instanceof \Throwable && $response->successful())
                ? collect($response->json('MediaContainer.Metadata') ?? [])->map(fn (array $ep): array => [
                    'season' => $ep['parentIndex'] ?? 0,
                    'episode' => $ep['index'] ?? 0,
                    'title' => $ep['title'] ?? 'Unknown',
                ])->all()
                : [];

            return [
                'name' => $server['name'],
                'clientIdentifier' => $server['clientIdentifier'],
                'owned' => $server['owned'],
                'uri' => $server['uri'],
                'show' => [
                    'title' => $server['match']['title'],
                    'year' => $server['match']['year'] ?? null,
                    'ratingKey' => $server['match']['ratingKey'],
                ],
                'episodes' => $episodes,
            ];
        });
    }

    /**
     * Create an HTTP client with the required Plex headers.
     */
    private function client(?string $token = null): PendingRequest
    {
        $headers = [
            'Accept' => 'application/json',
            'X-Plex-Client-Identifier' => $this->clientIdentifier,
            'X-Plex-Product' => $this->productName,
            'X-Plex-Version' => '1.0.0',
            'X-Plex-Platform' => PHP_OS_FAMILY,
            'X-Plex-Device-Name' => $this->productName,
        ];

        if ($token) {
            $headers['X-Plex-Token'] = $token;
        }

        return Http::withHeaders($headers)->throw();
    }
}
