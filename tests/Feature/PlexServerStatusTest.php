<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();

    config([
        'services.plex.client_identifier' => 'test-client-id',
        'services.plex.product_name' => 'Lund',
        'services.plex.server_identifier' => 'test-server-123',
        'services.plex.seed_token' => 'admin-seed-token',
    ]);
});

it('displays online plex servers', function () {
    $user = User::factory()->withPlex()->create();

    Http::fake([
        'clients.plex.tv/api/v2/resources*' => Http::response([
            [
                'name' => 'Home Server',
                'clientIdentifier' => 'server-home',
                'accessToken' => 'token-home',
                'owned' => true,
                'provides' => 'server',
                'presence' => true,
                'connections' => [
                    ['uri' => 'http://home.example.com:32400', 'local' => false],
                ],
            ],
            [
                'name' => 'Friend Server',
                'clientIdentifier' => 'server-friend',
                'accessToken' => 'token-friend',
                'owned' => false,
                'provides' => 'server',
                'presence' => true,
                'connections' => [
                    ['uri' => 'http://friend.example.com:32400', 'local' => false],
                ],
            ],
        ]),
    ]);

    $this->actingAs($user);

    Livewire::test('plex.server-status')
        ->assertSee('Home Server')
        ->assertSee('Friend Server')
        ->assertSee('Owned');
});

it('caches server status for 5 minutes', function () {
    $user = User::factory()->withPlex()->create();

    Http::fake([
        'clients.plex.tv/api/v2/resources*' => Http::response([
            [
                'name' => 'Cached Server',
                'clientIdentifier' => 'server-cached',
                'accessToken' => 'token-cached',
                'owned' => true,
                'provides' => 'server',
                'presence' => true,
                'connections' => [
                    ['uri' => 'http://cached.example.com:32400', 'local' => false],
                ],
            ],
        ]),
    ]);

    $this->actingAs($user);

    // First load
    Livewire::test('plex.server-status')
        ->assertSee('Cached Server');

    // Verify cache was populated
    expect(Cache::has('plex:server-status'))->toBeTrue();
});

it('returns cached data without making http requests', function () {
    $user = User::factory()->withPlex()->create();

    // Pre-populate the cache
    Cache::put('plex:server-status', collect([
        [
            'name' => 'Pre-Cached Server',
            'clientIdentifier' => 'server-precached',
            'owned' => false,
        ],
    ]), now()->addMinutes(5));

    $this->actingAs($user);

    Livewire::test('plex.server-status')
        ->assertSee('Pre-Cached Server');

    // Verify no HTTP requests were made
    Http::assertNothingSent();
});

it('shows empty state when no servers available', function () {
    $user = User::factory()->withPlex()->create();

    Http::fake([
        'clients.plex.tv/api/v2/resources*' => Http::response([]),
    ]);

    $this->actingAs($user);

    Livewire::test('plex.server-status')
        ->assertSee('No servers available');
});

it('shows empty state when seed token is not configured', function () {
    config(['services.plex.seed_token' => null]);

    $user = User::factory()->withPlex()->create();

    $this->actingAs($user);

    Livewire::test('plex.server-status')
        ->assertSee('No servers available');

    Http::assertNothingSent();
});

it('is displayed on the dashboard', function () {
    $user = User::factory()->withPlex()->create();

    $this->actingAs($user);

    $response = $this->get(route('home'));

    $response->assertSeeLivewire('plex.server-status');
});
