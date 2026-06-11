<?php

declare(strict_types=1);

namespace App\Services\ThirdParty;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class BYSHService
{
    private const BASE_URL = 'https://bytesized-hosting.com/api/v1';

    /**
     * Get the Bytesized Hosting account holder details.
     *
     * @return array<string, mixed>
     */
    public function user(): array
    {
        $response = $this->client()
            ->get(self::BASE_URL.'/user.json');

        $response->throw();

        return $response->json();
    }

    /**
     * Get usage and allocation data for every hosting account on the plan.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function accounts(): Collection
    {
        $response = $this->client()
            ->get(self::BASE_URL.'/accounts.json');

        $response->throw();

        return collect($response->json());
    }

    private function client(): PendingRequest
    {
        return Http::resilient()
            ->acceptJson()
            ->timeout(15)
            ->withQueryParameters(['api_key' => config('services.bysh.api_key')]);
    }
}
