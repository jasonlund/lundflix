<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class PlexService
{
    private const BASE_URL = 'https://clients.plex.tv/api/v2';

    public const TOKEN_LIFETIME_DAYS = 7;

    private string $clientIdentifier;

    private string $productName;

    private string $serverIdentifier;

    private string $privateKey;

    private string $keyId;

    public function __construct()
    {
        $this->clientIdentifier = config('services.plex.client_identifier');
        $this->productName = config('services.plex.product_name');
        $this->serverIdentifier = config('services.plex.server_identifier');
        $this->privateKey = config('services.plex.private_key');
        $this->keyId = config('services.plex.key_id');
    }

    /**
     * Create a new PIN for JWT authentication.
     * Includes JWK (public key) in the request body.
     *
     * @return array{id: int, code: string}
     */
    public function createPin(): array
    {
        $response = $this->client()
            ->asJson()
            ->post(self::BASE_URL.'/pins', [
                'strong' => true,
                'jwk' => $this->getJwk(),
            ]);

        return [
            'id' => $response->json('id'),
            'code' => $response->json('code'),
        ];
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
     * Verify that a PIN was claimed by the user.
     * Returns true if the user authenticated and the PIN is now linked.
     */
    public function verifyPinClaimed(int $pinId): bool
    {
        $response = $this->client()
            ->get(self::BASE_URL."/pins/{$pinId}");

        return $response->json('authToken') !== null;
    }

    /**
     * Refresh the Plex JWT token using nonce-based flow.
     * Call this when token expires (every 7 days).
     */
    public function refreshToken(): string
    {
        // Step 1: Get a nonce
        $nonceResponse = $this->client()
            ->get(self::BASE_URL.'/auth/nonce');

        $nonce = $nonceResponse->json('nonce');

        // Step 2: Create device JWT with nonce
        $deviceJwt = $this->createDeviceJwt($nonce);

        // Step 3: Exchange for new Plex token
        $tokenResponse = $this->client()
            ->asJson()
            ->post(self::BASE_URL.'/auth/token', [
                'jwt' => $deviceJwt,
            ]);

        return $tokenResponse->json('auth_token');
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
     * Make an authenticated request, automatically refreshing the token on 498 expiry.
     *
     * @param  array<string, mixed>  $options
     */
    public function authenticatedRequest(User $user, string $method, string $url, array $options = []): Response
    {
        try {
            return $this->client($user->plex_token)->$method($url, $options);
        } catch (RequestException $e) {
            if ($e->response->status() === 498) {
                $newToken = $this->refreshToken();
                $user->update([
                    'plex_token' => $newToken,
                    'plex_token_expires_at' => now()->addDays(self::TOKEN_LIFETIME_DAYS),
                ]);

                return $this->client($newToken)->$method($url, $options);
            }

            throw $e;
        }
    }

    /**
     * Get the JWK (JSON Web Key) representation of our public key.
     *
     * @return array{kty: string, crv: string, x: string, kid: string, alg: string}
     */
    private function getJwk(): array
    {
        $privateKeyBytes = base64_decode($this->privateKey);
        // ED25519 private key is 64 bytes: first 32 are seed, last 32 are public key
        $publicKeyBytes = substr($privateKeyBytes, 32, 32);

        return [
            'kty' => 'OKP',
            'crv' => 'Ed25519',
            'x' => rtrim(strtr(base64_encode($publicKeyBytes), '+/', '-_'), '='),
            'kid' => $this->keyId,
            'alg' => 'EdDSA',
        ];
    }

    /**
     * Create a signed device JWT for Plex authentication.
     */
    private function createDeviceJwt(?string $nonce = null): string
    {
        $now = time();

        $payload = [
            'aud' => 'plex.tv',
            'iss' => $this->clientIdentifier,
            'iat' => $now,
            'exp' => $now + 300, // 5 minutes
        ];

        if ($nonce !== null) {
            $payload['nonce'] = $nonce;
            $payload['scope'] = 'username,email';
        }

        // firebase/php-jwt expects base64-encoded key string for EdDSA
        return JWT::encode($payload, $this->privateKey, 'EdDSA', $this->keyId);
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
