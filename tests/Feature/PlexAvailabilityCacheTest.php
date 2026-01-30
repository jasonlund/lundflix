<?php

use App\Models\Movie;
use App\Models\Show;
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
    ]);
});

describe('movie plex availability caching', function () {
    it('caches plex availability data for movies', function () {
        $user = User::factory()->withPlex()->create();
        $movie = Movie::factory()->create(['imdb_id' => 'tt1375666']);

        Http::fake([
            'metadata.provider.plex.tv/library/metadata/matches*' => Http::response([
                'MediaContainer' => [
                    'Metadata' => [['guid' => 'plex://movie/abc123']],
                ],
            ]),
            'clients.plex.tv/api/v2/resources*' => Http::response([
                [
                    'name' => 'Test Server',
                    'clientIdentifier' => 'server-test',
                    'accessToken' => 'token-test',
                    'owned' => true,
                    'provides' => 'server',
                    'presence' => true,
                    'connections' => [
                        ['uri' => 'http://test.example.com:32400', 'local' => false],
                    ],
                ],
            ]),
            'http://test.example.com:32400/library/all*' => Http::response([
                'MediaContainer' => [
                    'Metadata' => [
                        ['title' => 'Inception', 'year' => 2010, 'ratingKey' => '12345'],
                    ],
                ],
            ]),
        ]);

        $this->actingAs($user);

        // First load - should make HTTP requests
        Livewire::test('movies.plex-availability', ['movie' => $movie])
            ->assertSee('Test Server');

        // Verify cache was populated
        $cacheKey = "plex:movie:{$user->id}:{$movie->id}";
        expect(Cache::has($cacheKey))->toBeTrue();
    });

    it('returns cached data on subsequent loads', function () {
        $user = User::factory()->withPlex()->create();
        $movie = Movie::factory()->create(['imdb_id' => 'tt1375666']);

        // Pre-populate the cache
        $cacheKey = "plex:movie:{$user->id}:{$movie->id}";
        Cache::put($cacheKey, collect([
            [
                'name' => 'Cached Server',
                'clientIdentifier' => 'cached-server',
                'owned' => true,
            ],
        ]), now()->addMinutes(10));

        $this->actingAs($user);

        // Should use cached data without making HTTP requests
        Livewire::test('movies.plex-availability', ['movie' => $movie])
            ->assertSee('Cached Server');

        // Verify no HTTP requests were made
        Http::assertNothingSent();
    });

    it('isolates cache by user', function () {
        $user1 = User::factory()->withPlex()->create();
        $user2 = User::factory()->withPlex()->create();
        $movie = Movie::factory()->create(['imdb_id' => 'tt1375666']);

        // Cache data for user1
        Cache::put("plex:movie:{$user1->id}:{$movie->id}", collect([
            ['name' => 'User 1 Server', 'clientIdentifier' => 'server-1', 'owned' => true],
        ]), now()->addMinutes(10));

        // Cache data for user2
        Cache::put("plex:movie:{$user2->id}:{$movie->id}", collect([
            ['name' => 'User 2 Server', 'clientIdentifier' => 'server-2', 'owned' => false],
        ]), now()->addMinutes(10));

        // User 1 should see their cached data
        $this->actingAs($user1);
        Livewire::test('movies.plex-availability', ['movie' => $movie])
            ->assertSee('User 1 Server')
            ->assertDontSee('User 2 Server');

        // User 2 should see their cached data
        $this->actingAs($user2);
        Livewire::test('movies.plex-availability', ['movie' => $movie])
            ->assertSee('User 2 Server')
            ->assertDontSee('User 1 Server');
    });

    it('returns empty when user has no plex token', function () {
        $user = User::factory()->create(['plex_token' => null]);
        $movie = Movie::factory()->create();

        $this->actingAs($user);

        Livewire::test('movies.plex-availability', ['movie' => $movie])
            ->assertSee('Not available on any servers');

        Http::assertNothingSent();
    });
});

describe('show plex availability caching', function () {
    it('caches plex availability data for shows', function () {
        $user = User::factory()->withPlex()->create();
        $show = Show::factory()->create(['imdb_id' => 'tt31987295']);

        Http::fake([
            'metadata.provider.plex.tv/library/metadata/matches*' => Http::response([
                'MediaContainer' => [
                    'Metadata' => [['guid' => 'plex://show/abc123']],
                ],
            ]),
            'clients.plex.tv/api/v2/resources*' => Http::response([
                [
                    'name' => 'Test Server',
                    'clientIdentifier' => 'server-test',
                    'accessToken' => 'token-test',
                    'owned' => true,
                    'provides' => 'server',
                    'presence' => true,
                    'connections' => [
                        ['uri' => 'http://test.example.com:32400', 'local' => false],
                    ],
                ],
            ]),
            'http://test.example.com:32400/library/all*' => Http::response([
                'MediaContainer' => [
                    'Metadata' => [
                        ['title' => 'Test Show', 'year' => 2025, 'ratingKey' => '12345'],
                    ],
                ],
            ]),
            'http://test.example.com:32400/library/metadata/12345/allLeaves' => Http::response([
                'MediaContainer' => [
                    'Metadata' => [
                        ['parentIndex' => 1, 'index' => 1, 'title' => 'Episode One'],
                    ],
                ],
            ]),
        ]);

        $this->actingAs($user);

        Livewire::test('shows.plex-availability', ['show' => $show])
            ->assertSee('Test Server');

        // Verify cache was populated
        $cacheKey = "plex:show:{$user->id}:{$show->id}";
        expect(Cache::has($cacheKey))->toBeTrue();
    });

    it('returns cached data on subsequent loads for shows', function () {
        $user = User::factory()->withPlex()->create();
        $show = Show::factory()->create(['imdb_id' => 'tt31987295']);

        // Pre-populate the cache
        $cacheKey = "plex:show:{$user->id}:{$show->id}";
        Cache::put($cacheKey, collect([
            [
                'name' => 'Cached Show Server',
                'clientIdentifier' => 'cached-server',
                'owned' => true,
                'episodes' => [
                    ['season' => 1, 'episode' => 1, 'title' => 'Cached Episode'],
                ],
            ],
        ]), now()->addMinutes(10));

        $this->actingAs($user);

        Livewire::test('shows.plex-availability', ['show' => $show])
            ->assertSee('Cached Show Server');

        Http::assertNothingSent();
    });

    it('returns empty when show has no imdb_id', function () {
        $user = User::factory()->withPlex()->create();
        $show = Show::factory()->create(['imdb_id' => null]);

        $this->actingAs($user);

        Livewire::test('shows.plex-availability', ['show' => $show])
            ->assertSee('Not available on any servers');

        Http::assertNothingSent();
    });
});
