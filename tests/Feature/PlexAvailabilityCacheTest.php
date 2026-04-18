<?php

use App\Models\Episode;
use App\Models\Movie;
use App\Models\PlexMediaServer;
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

        PlexMediaServer::factory()->create([
            'client_identifier' => 'server-test',
            'visible' => true,
        ]);

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
            'http://test.example.com:32400/library/metadata/12345' => Http::response([
                'MediaContainer' => [
                    'Metadata' => [
                        [
                            'title' => 'Inception',
                            'year' => 2010,
                            'ratingKey' => '12345',
                            'duration' => 8880000,
                            'Media' => [['videoResolution' => '1080', 'videoCodec' => 'h264']],
                        ],
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

        PlexMediaServer::factory()->create([
            'client_identifier' => 'cached-server',
            'visible' => true,
        ]);

        // Pre-populate the cache
        $cacheKey = "plex:movie:{$user->id}:{$movie->id}";
        Cache::put($cacheKey, collect([
            [
                'name' => 'Cached Server',
                'clientIdentifier' => 'cached-server',
                'owned' => true,
                'match' => ['ratingKey' => '99'],
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

        PlexMediaServer::factory()->create([
            'client_identifier' => 'server-1',
            'visible' => true,
        ]);

        PlexMediaServer::factory()->create([
            'client_identifier' => 'server-2',
            'visible' => true,
        ]);

        // Cache data for user1
        Cache::put("plex:movie:{$user1->id}:{$movie->id}", collect([
            ['name' => 'User 1 Server', 'clientIdentifier' => 'server-1', 'owned' => true, 'match' => ['ratingKey' => '1']],
        ]), now()->addMinutes(10));

        // Cache data for user2
        Cache::put("plex:movie:{$user2->id}:{$movie->id}", collect([
            ['name' => 'User 2 Server', 'clientIdentifier' => 'server-2', 'owned' => false, 'match' => ['ratingKey' => '2']],
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
            ->assertSee('Not available on any Plex server.');

        Http::assertNothingSent();
    });
});

describe('show plex availability caching', function () {
    it('caches plex availability data for shows', function () {
        $user = User::factory()->withPlex()->create();
        $show = Show::factory()->create(['imdb_id' => 'tt31987295']);

        PlexMediaServer::factory()->create([
            'client_identifier' => 'server-test',
            'visible' => true,
        ]);

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
            'http://test.example.com:32400/library/metadata/12345' => Http::response([
                'MediaContainer' => [
                    'Metadata' => [
                        ['title' => 'Test Show', 'year' => 2025, 'ratingKey' => '12345'],
                    ],
                ],
            ]),
            'http://test.example.com:32400/library/metadata/12345/allLeaves' => Http::response([
                'MediaContainer' => [
                    'Metadata' => [
                        ['parentIndex' => 1, 'index' => 1, 'title' => 'Episode One', 'ratingKey' => '100'],
                    ],
                ],
            ]),
        ]);

        $this->actingAs($user);

        Livewire::test('shows.plex-availability', ['show' => $show])
            ->assertSuccessful()
            ->assertSeeHtml('server-test');

        // Verify cache was populated
        $cacheKey = "plex:show:{$user->id}:{$show->id}";
        expect(Cache::has($cacheKey))->toBeTrue();
    });

    it('returns cached data on subsequent loads for shows', function () {
        $user = User::factory()->withPlex()->create();
        $show = Show::factory()->create(['imdb_id' => 'tt31987295']);

        PlexMediaServer::factory()->create([
            'client_identifier' => 'cached-server',
            'visible' => true,
        ]);

        // Pre-populate the cache
        $cacheKey = "plex:show:{$user->id}:{$show->id}";
        Cache::put($cacheKey, collect([
            [
                'name' => 'Cached Show Server',
                'clientIdentifier' => 'cached-server',
                'owned' => true,
                'episodes' => [
                    ['season' => 1, 'episode' => 1, 'title' => 'Cached Episode', 'ratingKey' => '100'],
                ],
            ],
        ]), now()->addMinutes(10));

        $this->actingAs($user);

        Livewire::test('shows.plex-availability', ['show' => $show])
            ->assertSuccessful()
            ->assertSeeHtml('cached-server');

        Http::assertNothingSent();
    });

    it('returns empty when show has no imdb_id', function () {
        $user = User::factory()->withPlex()->create();
        $show = Show::factory()->create(['imdb_id' => null]);

        $this->actingAs($user);

        Livewire::test('shows.plex-availability', ['show' => $show])
            ->assertSee('Availability')
            ->assertDontSeeHtml('flux:avatar');

        Http::assertNothingSent();
    });

    it('dispatches plex-show-loaded event with enriched episode availability', function () {
        $user = User::factory()->withPlex()->create();
        $show = Show::factory()->create(['imdb_id' => 'tt31987295']);

        PlexMediaServer::factory()->create([
            'client_identifier' => 'server-test',
            'owner_thumb' => 'https://plex.tv/thumb.jpg',
            'visible' => true,
        ]);

        $cacheKey = "plex:show:{$user->id}:{$show->id}";
        Cache::put($cacheKey, collect([
            [
                'name' => 'Test Server',
                'clientIdentifier' => 'server-test',
                'owned' => true,
                'episodes' => [
                    ['season' => 1, 'episode' => 1, 'title' => 'Episode One', 'ratingKey' => '100', 'duration' => 3600000, 'videoResolution' => '1080'],
                    ['season' => 1, 'episode' => 2, 'title' => 'Episode Two', 'ratingKey' => '101', 'duration' => 2700000, 'videoResolution' => '4k'],
                ],
            ],
        ]), now()->addMinutes(10));

        $this->actingAs($user);

        $component = Livewire::test('shows.plex-availability', ['show' => $show]);

        $component->assertDispatched('plex-show-loaded');

        $availability = $component->instance()->episodeAvailability();

        expect($availability)->toHaveKey('S01E01')
            ->and($availability['S01E01'][0]['name'])->toBe('Test Server')
            ->and($availability['S01E01'][0]['clientIdentifier'])->toBe('server-test')
            ->and($availability['S01E01'][0]['ownerThumb'])->toBe('https://plex.tv/thumb.jpg')
            ->and($availability['S01E01'][0]['isOnline'])->toBeBool()
            ->and($availability['S01E01'][0]['videoResolution'])->toBe('1080p')
            ->and($availability['S01E01'][0]['duration'])->toBe(3600000)
            ->and($availability['S01E01'][0]['webUrl'])->toContain('server-test')
            ->and($availability['S01E01'][0]['webUrl'])->toContain('100')
            ->and($availability['S01E02'][0]['videoResolution'])->toBe('4K');
    });

    it('excludes non-visible servers from episode availability dispatch', function () {
        $user = User::factory()->withPlex()->create();
        $show = Show::factory()->create(['imdb_id' => 'tt31987295']);

        PlexMediaServer::factory()->create([
            'client_identifier' => 'visible-server',
            'visible' => true,
        ]);

        PlexMediaServer::factory()->create([
            'client_identifier' => 'hidden-server',
            'visible' => false,
        ]);

        $cacheKey = "plex:show:{$user->id}:{$show->id}";
        Cache::put($cacheKey, collect([
            [
                'name' => 'Visible Server',
                'clientIdentifier' => 'visible-server',
                'owned' => true,
                'episodes' => [
                    ['season' => 1, 'episode' => 1, 'title' => 'Ep 1', 'ratingKey' => '100'],
                ],
            ],
            [
                'name' => 'Hidden Server',
                'clientIdentifier' => 'hidden-server',
                'owned' => true,
                'episodes' => [
                    ['season' => 1, 'episode' => 1, 'title' => 'Ep 1', 'ratingKey' => '200'],
                ],
            ],
        ]), now()->addMinutes(10));

        $this->actingAs($user);

        $component = Livewire::test('shows.plex-availability', ['show' => $show]);
        $availability = $component->instance()->episodeAvailability();

        expect($availability['S01E01'])->toHaveCount(1)
            ->and($availability['S01E01'][0]['name'])->toBe('Visible Server');
    });

    it('shows check badge when server has all aired episodes', function () {
        $user = User::factory()->withPlex()->create();
        $show = Show::factory()->create(['imdb_id' => 'tt31987295']);

        PlexMediaServer::factory()->create([
            'client_identifier' => 'server-complete',
            'visible' => true,
        ]);

        Episode::factory()->count(3)->sequence(
            ['season' => 1, 'number' => 1, 'airdate' => now()->subDays(3), 'airtime' => null],
            ['season' => 1, 'number' => 2, 'airdate' => now()->subDays(2), 'airtime' => null],
            ['season' => 1, 'number' => 3, 'airdate' => now()->subDay(), 'airtime' => null],
        )->create(['show_id' => $show->id]);

        $cacheKey = "plex:show:{$user->id}:{$show->id}";
        Cache::put($cacheKey, collect([
            [
                'name' => 'Complete Server',
                'clientIdentifier' => 'server-complete',
                'owned' => true,
                'episodes' => [
                    ['season' => 1, 'episode' => 1, 'title' => 'Ep 1', 'ratingKey' => '100'],
                    ['season' => 1, 'episode' => 2, 'title' => 'Ep 2', 'ratingKey' => '101'],
                    ['season' => 1, 'episode' => 3, 'title' => 'Ep 3', 'ratingKey' => '102'],
                ],
            ],
        ]), now()->addMinutes(10));

        $this->actingAs($user);

        $component = Livewire::test('shows.plex-availability', ['show' => $show]);
        $displayData = $component->instance()->serverDisplayData;

        expect($displayData[0]['hasAllAired'])->toBeTrue()
            ->and($displayData[0]['tooltip'])->toContain('All episodes');
    });

    it('shows episode count badge when server has partial episodes', function () {
        $user = User::factory()->withPlex()->create();
        $show = Show::factory()->create(['imdb_id' => 'tt31987295']);

        PlexMediaServer::factory()->create([
            'client_identifier' => 'server-partial',
            'visible' => true,
        ]);

        Episode::factory()->count(3)->sequence(
            ['season' => 1, 'number' => 1, 'airdate' => now()->subDays(3), 'airtime' => null],
            ['season' => 1, 'number' => 2, 'airdate' => now()->subDays(2), 'airtime' => null],
            ['season' => 1, 'number' => 3, 'airdate' => now()->subDay(), 'airtime' => null],
        )->create(['show_id' => $show->id]);

        $cacheKey = "plex:show:{$user->id}:{$show->id}";
        Cache::put($cacheKey, collect([
            [
                'name' => 'Partial Server',
                'clientIdentifier' => 'server-partial',
                'owned' => true,
                'episodes' => [
                    ['season' => 1, 'episode' => 1, 'title' => 'Ep 1', 'ratingKey' => '100'],
                    ['season' => 1, 'episode' => 2, 'title' => 'Ep 2', 'ratingKey' => '101'],
                ],
            ],
        ]), now()->addMinutes(10));

        $this->actingAs($user);

        $component = Livewire::test('shows.plex-availability', ['show' => $show]);
        $displayData = $component->instance()->serverDisplayData;

        expect($displayData[0]['hasAllAired'])->toBeFalse()
            ->and($displayData[0]['episodeCount'])->toBe(2)
            ->and($displayData[0]['tooltip'])->toContain('2 of 3');
    });

    it('does not count unaired plex episodes toward aired completeness', function () {
        $user = User::factory()->withPlex()->create();
        $show = Show::factory()->create(['imdb_id' => 'tt31987295']);

        PlexMediaServer::factory()->create([
            'client_identifier' => 'server-future-only',
            'visible' => true,
        ]);

        Episode::factory()->count(4)->sequence(
            ['season' => 1, 'number' => 1, 'airdate' => now()->subDays(3), 'airtime' => null],
            ['season' => 1, 'number' => 2, 'airdate' => now()->subDays(2), 'airtime' => null],
            ['season' => 1, 'number' => 3, 'airdate' => now()->addWeek(), 'airtime' => null],
            ['season' => 1, 'number' => 4, 'airdate' => now()->addWeeks(2), 'airtime' => null],
        )->create(['show_id' => $show->id]);

        $cacheKey = "plex:show:{$user->id}:{$show->id}";
        Cache::put($cacheKey, collect([
            [
                'name' => 'Future Only',
                'clientIdentifier' => 'server-future-only',
                'owned' => true,
                'show' => ['ratingKey' => '999'],
                'episodes' => [
                    ['season' => 1, 'episode' => 3, 'title' => 'Ep 3', 'ratingKey' => '103'],
                    ['season' => 1, 'episode' => 4, 'title' => 'Ep 4', 'ratingKey' => '104'],
                ],
            ],
        ]), now()->addMinutes(10));

        $this->actingAs($user);

        $component = Livewire::test('shows.plex-availability', ['show' => $show]);
        $displayData = $component->instance()->serverDisplayData;

        expect($displayData[0]['hasAllAired'])->toBeFalse()
            ->and($displayData[0]['episodeCount'])->toBe(0)
            ->and($displayData[0]['airedCount'])->toBe(2)
            ->and($displayData[0]['tooltip'])->toContain('0 of 2');
    });

    it('uses owner_thumb from PlexMediaServer model', function () {
        $user = User::factory()->withPlex()->create();
        $show = Show::factory()->create(['imdb_id' => 'tt31987295']);

        PlexMediaServer::factory()->create([
            'client_identifier' => 'server-with-avatar',
            'owner_thumb' => 'https://plex.tv/users/123/avatar',
            'visible' => true,
        ]);

        $cacheKey = "plex:show:{$user->id}:{$show->id}";
        Cache::put($cacheKey, collect([
            [
                'name' => 'Avatar Server',
                'clientIdentifier' => 'server-with-avatar',
                'owned' => true,
                'episodes' => [
                    ['season' => 1, 'episode' => 1, 'title' => 'Ep 1', 'ratingKey' => '100'],
                ],
            ],
        ]), now()->addMinutes(10));

        $this->actingAs($user);

        $component = Livewire::test('shows.plex-availability', ['show' => $show]);
        $displayData = $component->instance()->serverDisplayData;

        expect($displayData[0]['ownerThumb'])->toBe('https://plex.tv/users/123/avatar');
    });

    it('shows not available when server has no visible PlexMediaServer record', function () {
        $user = User::factory()->withPlex()->create();
        $show = Show::factory()->create(['imdb_id' => 'tt31987295']);

        $cacheKey = "plex:show:{$user->id}:{$show->id}";
        Cache::put($cacheKey, collect([
            [
                'name' => 'Unknown Server',
                'clientIdentifier' => 'server-no-record',
                'owned' => true,
                'episodes' => [
                    ['season' => 1, 'episode' => 1, 'title' => 'Ep 1', 'ratingKey' => '100'],
                ],
            ],
        ]), now()->addMinutes(10));

        $this->actingAs($user);

        $component = Livewire::test('shows.plex-availability', ['show' => $show]);

        expect($component->instance()->serverDisplayData)->toBeEmpty();
        $component->assertDontSeeHtml('data-flux-avatar');
    });

    it('filters out non-visible servers even if they exist in database', function () {
        $user = User::factory()->withPlex()->create();
        $show = Show::factory()->create(['imdb_id' => 'tt31987295']);

        PlexMediaServer::factory()->create([
            'client_identifier' => 'server-hidden',
            'visible' => false,
        ]);

        $cacheKey = "plex:show:{$user->id}:{$show->id}";
        Cache::put($cacheKey, collect([
            [
                'name' => 'Hidden Server',
                'clientIdentifier' => 'server-hidden',
                'owned' => true,
                'episodes' => [
                    ['season' => 1, 'episode' => 1, 'title' => 'Ep 1', 'ratingKey' => '100'],
                ],
            ],
        ]), now()->addMinutes(10));

        $this->actingAs($user);

        $component = Livewire::test('shows.plex-availability', ['show' => $show]);

        expect($component->instance()->serverDisplayData)->toBeEmpty();
        $component->assertDontSeeHtml('data-flux-avatar');
    });

    it('excludes unaired episodes from aired count', function () {
        $user = User::factory()->withPlex()->create();
        $show = Show::factory()->create(['imdb_id' => 'tt31987295']);

        PlexMediaServer::factory()->create([
            'client_identifier' => 'server-full',
            'visible' => true,
        ]);

        Episode::factory()->count(4)->sequence(
            ['season' => 1, 'number' => 1, 'airdate' => now()->subDays(3), 'airtime' => null],
            ['season' => 1, 'number' => 2, 'airdate' => now()->subDays(2), 'airtime' => null],
            ['season' => 1, 'number' => 3, 'airdate' => now()->addWeek(), 'airtime' => null],
            ['season' => 1, 'number' => 4, 'airdate' => null, 'airtime' => null],
        )->create(['show_id' => $show->id]);

        // Server has both aired episodes — should show as complete
        $cacheKey = "plex:show:{$user->id}:{$show->id}";
        Cache::put($cacheKey, collect([
            [
                'name' => 'Full Server',
                'clientIdentifier' => 'server-full',
                'owned' => true,
                'episodes' => [
                    ['season' => 1, 'episode' => 1, 'title' => 'Ep 1', 'ratingKey' => '100'],
                    ['season' => 1, 'episode' => 2, 'title' => 'Ep 2', 'ratingKey' => '101'],
                ],
            ],
        ]), now()->addMinutes(10));

        $this->actingAs($user);

        $component = Livewire::test('shows.plex-availability', ['show' => $show]);
        $displayData = $component->instance()->serverDisplayData;

        expect($displayData[0]['hasAllAired'])->toBeTrue()
            ->and($displayData[0]['tooltip'])->toContain('All episodes');
    });
});
