<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
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
