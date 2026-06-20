<?php

use App\Services\ThirdParty\BYSHService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.bysh.api_key', 'test-key');
});

it('fetches the account holder and sends the api key as a query param', function () {
    Http::fake([
        'bytesized-hosting.com/api/v1/user.json*' => Http::response([
            'login' => 'lundberg',
            'email' => 'host@example.com',
            'current_balance' => '12.50',
            'number_of_active_accounts' => 2,
        ]),
    ]);

    $user = app(BYSHService::class)->user();

    expect($user['login'])->toBe('lundberg')
        ->and($user['number_of_active_accounts'])->toBe(2);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api_key=test-key'));
});

it('fetches accounts as a collection', function () {
    Http::fake([
        'bytesized-hosting.com/api/v1/accounts.json*' => Http::response([
            [
                'server_name' => 'sphinx',
                'disk_quota' => 1000,
                'total_storage' => 153600.0,
                'bandwidth_quota' => 49254,
                'total_bandwidth' => 6.0,
                'pretty_disk_quota' => '1000 KB / 150 GB',
            ],
            [
                'server_name' => 'griffin',
                'disk_quota' => 2000,
            ],
        ]),
    ]);

    $accounts = app(BYSHService::class)->accounts();

    expect($accounts)->toBeInstanceOf(Collection::class)
        ->and($accounts)->toHaveCount(2)
        ->and($accounts->first()['server_name'])->toBe('sphinx');
});

it('throws on a failed response', function () {
    Http::fake([
        'bytesized-hosting.com/api/v1/*' => Http::response('Unauthorized', 401),
    ]);

    app(BYSHService::class)->accounts();
})->throws(RequestException::class);
