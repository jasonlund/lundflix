<?php

use App\Enums\RequestItemStatus;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\PlexMediaServer;
use App\Models\RequestItem;
use App\Models\Show;
use App\Notifications\PlexLibraryNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

function plexMovie(string $title, int $ratingKey, int $addedAt, ?int $year = 2024): array
{
    return [
        'type' => 'movie',
        'title' => $title,
        'year' => $year,
        'ratingKey' => (string) $ratingKey,
        'addedAt' => $addedAt,
    ];
}

function plexEpisode(
    string $showTitle,
    int $season,
    int $episode,
    int $ratingKey,
    int $grandparentRatingKey,
    int $addedAt,
    string $title = 'Episode',
): array {
    return [
        'type' => 'episode',
        'title' => $title,
        'grandparentTitle' => $showTitle,
        'parentIndex' => $season,
        'index' => $episode,
        'ratingKey' => (string) $ratingKey,
        'grandparentRatingKey' => (string) $grandparentRatingKey,
        'addedAt' => $addedAt,
    ];
}

function fakePlexMetadata(int $ratingKey, array $guids = []): array
{
    return [
        'MediaContainer' => [
            'Metadata' => [[
                'title' => 'Test Item',
                'ratingKey' => (string) $ratingKey,
                'Guid' => collect($guids)->map(fn (string $id): array => ['id' => $id])->all(),
            ]],
        ],
    ];
}

function fakeRecentlyAdded(array $items): array
{
    return ['MediaContainer' => ['Metadata' => $items]];
}

function fakeSections(array $sections = [['key' => '1', 'type' => 'movie'], ['key' => '2', 'type' => 'show']]): array
{
    return ['MediaContainer' => ['Directory' => $sections]];
}

function fakeSectionRoutes(string $uri, array $movieItems = [], array $episodeItems = []): array
{
    return [
        "{$uri}/library/sections" => Http::response(fakeSections()),
        "{$uri}/library/sections/1/recentlyAdded*" => Http::response(fakeRecentlyAdded($movieItems)),
        "{$uri}/library/sections/2/recentlyAdded*" => Http::response(fakeRecentlyAdded($episodeItems)),
    ];
}

function createPollableServer(array $attributes = []): PlexMediaServer
{
    return PlexMediaServer::factory()->pollRecentlyAdded()->create(array_merge([
        'uri' => 'http://plex.test:32400',
    ], $attributes));
}

function flushPollCacheKeys(): void
{
    Cache::flush();
}

beforeEach(function () {
    config([
        'services.plex.client_identifier' => 'test-client-id',
        'services.plex.product_name' => 'lundflix',
        'services.plex.server_identifier' => 'test-server',
        'services.plex.poll_debounce_seconds' => 300,
        'services.plex.poll_hard_deadline_seconds' => 900,
        'services.slack.enabled' => true,
        'services.slack.notifications.channel' => '#test-channel',
    ]);

    Notification::fake();
    flushPollCacheKeys();
});

afterEach(function () {
    flushPollCacheKeys();
});

it('skips servers with poll_recently_added disabled', function () {
    PlexMediaServer::factory()->create(['poll_recently_added' => false]);

    Http::fake();

    $this->artisan('plex:poll-library')->assertSuccessful();

    Http::assertNothingSent();
});

it('skips offline servers', function () {
    PlexMediaServer::factory()->pollRecentlyAdded()->offline()->create();

    Http::fake();

    $this->artisan('plex:poll-library')->assertSuccessful();

    Http::assertNothingSent();
});

it('processes movies immediately and fulfills matching requests', function () {
    $server = createPollableServer();
    $movie = Movie::factory()->create(['tmdb_id' => 27205]);
    $requestItem = RequestItem::factory()->pending()->forRequestable($movie)->create();

    $now = now()->timestamp;

    Http::fake(array_merge(
        fakeSectionRoutes($server->uri, movieItems: [plexMovie('Inception', 100, $now, 2010)]),
        [
            "{$server->uri}/library/metadata/100" => Http::response(
                fakePlexMetadata(100, ['tmdb://27205', 'imdb://tt1375666'])
            ),
        ],
    ));

    $this->artisan('plex:poll-library')->assertSuccessful();

    expect($requestItem->fresh()->status)->toBe(RequestItemStatus::Fulfilled);

    Notification::assertSentOnDemand(PlexLibraryNotification::class);
});

it('buffers episodes and does not process them until debounce window', function () {
    $server = createPollableServer();
    $now = now()->timestamp;

    Http::fake(fakeSectionRoutes($server->uri, episodeItems: [
        plexEpisode('Breaking Bad', 1, 1, 200, 50, $now),
        plexEpisode('Breaking Bad', 1, 2, 201, 50, $now),
    ]));

    $this->artisan('plex:poll-library')->assertSuccessful();

    Notification::assertNothingSent();

    $index = Cache::get("plex:poll:pending-index:{$server->client_identifier}");
    expect($index)->toContain('50');
});

it('harvests ripe shows after debounce window', function () {
    $server = createPollableServer();
    $show = Show::factory()->create(['tmdb_id' => 1396]);
    $episode = Episode::factory()->create(['show_id' => $show->id, 'season' => 1, 'number' => 1]);
    $requestItem = RequestItem::factory()->pending()->forRequestable($episode)->create();

    $cid = $server->client_identifier;
    $pastTime = now()->subMinutes(10)->timestamp;

    Cache::put("plex:poll:pending-index:{$cid}", ['50'], 1800);
    Cache::put("plex:poll:pending:{$cid}:50", [
        'server_name' => $server->name,
        'show_title' => 'Breaking Bad',
        'first_seen_at' => $pastTime,
        'last_seen_at' => $pastTime,
        'items' => [
            '200' => [
                'media_type' => 'episode',
                'title' => 'Pilot',
                'show_title' => 'Breaking Bad',
                'season' => 1,
                'episode_number' => 1,
                'rating_key' => '200',
                'grandparent_rating_key' => '50',
                'added_at' => $pastTime,
            ],
        ],
    ], 1800);

    Http::fake(array_merge(
        fakeSectionRoutes($server->uri),
        [
            "{$server->uri}/library/metadata/200" => Http::response(
                fakePlexMetadata(200, ['tmdb://1396'])
            ),
        ],
    ));

    $this->artisan('plex:poll-library')->assertSuccessful();

    expect($requestItem->fresh()->status)->toBe(RequestItemStatus::Fulfilled);

    Notification::assertSentOnDemand(PlexLibraryNotification::class);

    expect(Cache::get("plex:poll:pending-index:{$cid}"))->toBeNull();
});

it('does not harvest shows before debounce window', function () {
    $server = createPollableServer();
    $cid = $server->client_identifier;
    $recentTime = now()->subSeconds(30)->timestamp;

    Cache::put("plex:poll:pending-index:{$cid}", ['50'], 1800);
    Cache::put("plex:poll:pending:{$cid}:50", [
        'server_name' => $server->name,
        'show_title' => 'Breaking Bad',
        'first_seen_at' => $recentTime,
        'last_seen_at' => $recentTime,
        'items' => [
            '200' => [
                'media_type' => 'episode',
                'title' => 'Pilot',
                'show_title' => 'Breaking Bad',
                'season' => 1,
                'episode_number' => 1,
                'rating_key' => '200',
                'grandparent_rating_key' => '50',
                'added_at' => $recentTime,
            ],
        ],
    ], 1800);

    Http::fake(fakeSectionRoutes($server->uri));

    $this->artisan('plex:poll-library')->assertSuccessful();

    Notification::assertNothingSent();

    expect(Cache::get("plex:poll:pending-index:{$cid}"))->toContain('50');
});

it('harvests shows at hard deadline even if still receiving episodes', function () {
    $server = createPollableServer();
    $cid = $server->client_identifier;

    config(['services.plex.poll_hard_deadline_seconds' => 900]);

    $oldFirstSeen = now()->subMinutes(16)->timestamp;
    $recentLastSeen = now()->subSeconds(30)->timestamp;

    Cache::put("plex:poll:pending-index:{$cid}", ['50'], 1800);
    Cache::put("plex:poll:pending:{$cid}:50", [
        'server_name' => $server->name,
        'show_title' => 'Breaking Bad',
        'first_seen_at' => $oldFirstSeen,
        'last_seen_at' => $recentLastSeen,
        'items' => [
            '200' => [
                'media_type' => 'episode',
                'title' => 'Pilot',
                'show_title' => 'Breaking Bad',
                'season' => 1,
                'episode_number' => 1,
                'rating_key' => '200',
                'grandparent_rating_key' => '50',
                'added_at' => $oldFirstSeen,
            ],
        ],
    ], 1800);

    Http::fake(fakeSectionRoutes($server->uri));

    $this->artisan('plex:poll-library')->assertSuccessful();

    Notification::assertSentOnDemand(PlexLibraryNotification::class);
});

it('advances high water mark after processing', function () {
    $server = createPollableServer();
    $now = now()->timestamp;

    Http::fake(array_merge(
        fakeSectionRoutes($server->uri, movieItems: [plexMovie('Test Movie', 100, $now)]),
        [
            "{$server->uri}/library/metadata/100" => Http::response(
                fakePlexMetadata(100, ['tmdb://99999'])
            ),
        ],
    ));

    $this->artisan('plex:poll-library')->assertSuccessful();

    $hwm = Cache::get("plex:poll:hwm:{$server->client_identifier}");
    expect((int) $hwm)->toBe($now);
});

it('filters out items at or below the high water mark', function () {
    $server = createPollableServer();
    $cid = $server->client_identifier;
    $oldTimestamp = now()->subMinutes(10)->timestamp;

    Cache::forever("plex:poll:hwm:{$cid}", $oldTimestamp);

    Http::fake(array_merge(
        fakeSectionRoutes($server->uri, movieItems: [
            plexMovie('Old Movie', 100, $oldTimestamp - 60),
            plexMovie('New Movie', 101, $oldTimestamp + 60),
        ]),
        [
            "{$server->uri}/library/metadata/101" => Http::response(
                fakePlexMetadata(101, ['tmdb://88888'])
            ),
        ],
    ));

    $this->artisan('plex:poll-library')->assertSuccessful();

    Notification::assertSentOnDemand(
        PlexLibraryNotification::class,
        function (PlexLibraryNotification $notification) {
            return $notification->items->count() === 1
                && $notification->items->first()['title'] === 'New Movie';
        }
    );
});

it('keeps servers fully isolated with separate cache keys', function () {
    $serverA = createPollableServer(['name' => 'Server A', 'uri' => 'http://plex-a.test:32400']);
    $serverB = createPollableServer(['name' => 'Server B', 'uri' => 'http://plex-b.test:32400']);

    $now = now()->timestamp;

    Http::fake(array_merge(
        fakeSectionRoutes($serverA->uri, movieItems: [plexMovie('Movie A', 100, $now)]),
        fakeSectionRoutes($serverB->uri, movieItems: [plexMovie('Movie B', 200, $now)]),
        [
            "{$serverA->uri}/library/metadata/100" => Http::response(
                fakePlexMetadata(100, ['tmdb://11111'])
            ),
            "{$serverB->uri}/library/metadata/200" => Http::response(
                fakePlexMetadata(200, ['tmdb://22222'])
            ),
        ],
    ));

    $this->artisan('plex:poll-library')->assertSuccessful();

    $hwmA = Cache::get("plex:poll:hwm:{$serverA->client_identifier}");
    $hwmB = Cache::get("plex:poll:hwm:{$serverB->client_identifier}");

    expect((int) $hwmA)->toBe($now);
    expect((int) $hwmB)->toBe($now);

    Notification::assertSentOnDemand(PlexLibraryNotification::class, function (PlexLibraryNotification $notification) {
        return $notification->serverName === 'Server A';
    });

    Notification::assertSentOnDemand(PlexLibraryNotification::class, function (PlexLibraryNotification $notification) {
        return $notification->serverName === 'Server B';
    });
});

it('continues processing other servers when one fails', function () {
    $serverA = createPollableServer(['name' => 'Failing Server', 'uri' => 'http://failing.test:32400']);
    $serverB = createPollableServer(['name' => 'Working Server', 'uri' => 'http://working.test:32400']);

    $now = now()->timestamp;

    Http::fake(array_merge(
        ['http://failing.test:32400/library/sections' => Http::response(status: 500)],
        fakeSectionRoutes('http://working.test:32400', movieItems: [plexMovie('Movie B', 200, $now)]),
        [
            'http://working.test:32400/library/metadata/200' => Http::response(
                fakePlexMetadata(200, ['tmdb://22222'])
            ),
        ],
    ));

    $this->artisan('plex:poll-library')->assertSuccessful();

    Notification::assertSentOnDemand(PlexLibraryNotification::class, function (PlexLibraryNotification $notification) {
        return $notification->serverName === 'Working Server';
    });
});

it('restores server context and enriches plex library notifications with app links', function () {
    $server = createPollableServer(['name' => 'Main Plex']);
    $movie = Movie::factory()->create(['tmdb_id' => 27205]);
    $show = Show::factory()->create(['tmdb_id' => 1396]);
    $now = now()->timestamp;
    $pastTime = now()->subMinutes(10)->timestamp;
    $cid = $server->client_identifier;

    Cache::put("plex:poll:pending-index:{$cid}", ['50'], 1800);
    Cache::put("plex:poll:pending:{$cid}:50", [
        'server_name' => $server->name,
        'show_title' => 'Breaking Bad',
        'first_seen_at' => $pastTime,
        'last_seen_at' => $pastTime,
        'items' => [
            '200' => [
                'media_type' => 'episode',
                'title' => 'Pilot',
                'show_title' => 'Breaking Bad',
                'season' => 1,
                'episode_number' => 1,
                'rating_key' => '200',
                'grandparent_rating_key' => '50',
                'added_at' => $pastTime,
            ],
        ],
    ], 1800);

    Http::fake(array_merge(
        fakeSectionRoutes($server->uri, movieItems: [plexMovie('Inception', 100, $now, 2010)]),
        [
            "{$server->uri}/library/metadata/100" => Http::response(
                fakePlexMetadata(100, ['tmdb://27205'])
            ),
            "{$server->uri}/library/metadata/200" => Http::response(
                fakePlexMetadata(200, ['tmdb://1396'])
            ),
        ],
    ));

    $this->artisan('plex:poll-library')->assertSuccessful();

    Notification::assertSentOnDemand(PlexLibraryNotification::class, function (PlexLibraryNotification $notification) use ($movie, $server, $show) {
        $movieItem = $notification->items->firstWhere('media_type', 'movie');
        $episodeItem = $notification->items->firstWhere('media_type', 'episode');
        $payload = $notification->toSlack(new AnonymousNotifiable)->toArray();

        expect($notification->serverName)->toBe($server->name);
        expect($movieItem['url'])->toBe(route('movies.show', $movie));
        expect($episodeItem['show_url'])->toBe(route('shows.show', $show));
        expect($payload['text'])->toContain('Added to library on Main Plex');
        expect($payload['blocks'][0]['text']['text'])->toContain(route('movies.show', $movie));
        expect($payload['blocks'][0]['text']['text'])->toContain('#episode-s01e01');
        expect($payload['blocks'][0]['text']['text'])->toContain('Main Plex');
        expect($payload['unfurl_links'])->toBeFalse();
        expect($payload['unfurl_media'])->toBeFalse();

        return true;
    });
});

it('deduplicates items with same addedAt as high water mark using last-keys', function () {
    $server = createPollableServer();
    $cid = $server->client_identifier;
    $timestamp = now()->timestamp;

    Cache::forever("plex:poll:hwm:{$cid}", $timestamp);
    Cache::put("plex:poll:last-keys:{$cid}", ['100'], 120);

    Http::fake(array_merge(
        fakeSectionRoutes($server->uri, movieItems: [
            plexMovie('Already Seen', 100, $timestamp),
            plexMovie('Brand New', 101, $timestamp),
        ]),
        [
            "{$server->uri}/library/metadata/101" => Http::response(
                fakePlexMetadata(101, ['tmdb://77777'])
            ),
        ],
    ));

    $this->artisan('plex:poll-library')->assertSuccessful();

    Notification::assertSentOnDemand(
        PlexLibraryNotification::class,
        function (PlexLibraryNotification $notification) {
            return $notification->items->count() === 1
                && $notification->items->first()['title'] === 'Brand New';
        }
    );
});

it('does not send notification when no new items', function () {
    $server = createPollableServer();

    Http::fake(fakeSectionRoutes($server->uri));

    $this->artisan('plex:poll-library')->assertSuccessful();

    Notification::assertNothingSent();
});

it('merges last-keys across polls instead of overwriting', function () {
    $server = createPollableServer();
    $cid = $server->client_identifier;
    $timestamp = now()->timestamp;

    Cache::forever("plex:poll:hwm:{$cid}", $timestamp);
    Cache::forever("plex:poll:last-keys:{$cid}", ['100', '101']);

    Http::fake(array_merge(
        fakeSectionRoutes($server->uri, movieItems: [
            plexMovie('Already Seen A', 100, $timestamp),
            plexMovie('Already Seen B', 101, $timestamp),
            plexMovie('New At Same Timestamp', 102, $timestamp),
        ]),
        [
            "{$server->uri}/library/metadata/102" => Http::response(
                fakePlexMetadata(102, ['tmdb://66666'])
            ),
        ],
    ));

    $this->artisan('plex:poll-library')->assertSuccessful();

    $lastKeys = Cache::get("plex:poll:last-keys:{$cid}");
    expect($lastKeys)->toContain('100', '101', '102');
});

it('stores last-keys with forever TTL matching the high water mark', function () {
    $server = createPollableServer();
    $cid = $server->client_identifier;
    $now = now()->timestamp;

    Http::fake(array_merge(
        fakeSectionRoutes($server->uri, movieItems: [plexMovie('Test Movie', 100, $now)]),
        [
            "{$server->uri}/library/metadata/100" => Http::response(
                fakePlexMetadata(100, ['tmdb://99999'])
            ),
        ],
    ));

    $this->artisan('plex:poll-library')->assertSuccessful();

    $lastKeys = Cache::get("plex:poll:last-keys:{$cid}");
    expect($lastKeys)->toContain('100');

    $this->travel(20)->minutes();

    expect(Cache::get("plex:poll:last-keys:{$cid}"))->not->toBeNull();
});

it('advances high water mark from harvested episodes', function () {
    $server = createPollableServer();
    $cid = $server->client_identifier;
    $pastTime = now()->subMinutes(10)->timestamp;
    $episodeAddedAt = now()->subMinutes(8)->timestamp;

    Cache::forever("plex:poll:hwm:{$cid}", $pastTime);
    Cache::put("plex:poll:pending-index:{$cid}", ['50'], 1800);
    Cache::put("plex:poll:pending:{$cid}:50", [
        'server_name' => $server->name,
        'show_title' => 'Breaking Bad',
        'first_seen_at' => $pastTime,
        'last_seen_at' => $pastTime,
        'items' => [
            '200' => [
                'media_type' => 'episode',
                'title' => 'Pilot',
                'show_title' => 'Breaking Bad',
                'season' => 1,
                'episode_number' => 1,
                'rating_key' => '200',
                'grandparent_rating_key' => '50',
                'added_at' => $episodeAddedAt,
            ],
        ],
    ], 1800);

    Http::fake(fakeSectionRoutes($server->uri));

    $this->artisan('plex:poll-library')->assertSuccessful();

    $hwm = (int) Cache::get("plex:poll:hwm:{$cid}");
    expect($hwm)->toBe($episodeAddedAt);

    $lastKeys = Cache::get("plex:poll:last-keys:{$cid}");
    expect($lastKeys)->toContain('200');
});

it('uses configurable initial lookback window on first run', function () {
    config(['services.plex.poll_initial_lookback_seconds' => 600]);

    $server = createPollableServer();
    $cid = $server->client_identifier;
    $withinWindow = now()->subSeconds(500)->timestamp;
    $outsideWindow = now()->subSeconds(700)->timestamp;

    Http::fake(array_merge(
        fakeSectionRoutes($server->uri, movieItems: [
            plexMovie('Recent Movie', 100, $withinWindow),
            plexMovie('Old Movie', 101, $outsideWindow),
        ]),
        [
            "{$server->uri}/library/metadata/100" => Http::response(
                fakePlexMetadata(100, ['tmdb://99999'])
            ),
        ],
    ));

    $this->artisan('plex:poll-library')->assertSuccessful();

    Notification::assertSentOnDemand(
        PlexLibraryNotification::class,
        function (PlexLibraryNotification $notification) {
            return $notification->items->count() === 1
                && $notification->items->first()['title'] === 'Recent Movie';
        }
    );
});

it('ignores show and season type items from recentlyAdded', function () {
    $server = createPollableServer();
    $now = now()->timestamp;

    Http::fake(array_merge(
        fakeSectionRoutes($server->uri, movieItems: [
            ['type' => 'show', 'title' => 'Some Show', 'ratingKey' => '300', 'addedAt' => $now],
            ['type' => 'season', 'title' => 'Season 1', 'ratingKey' => '301', 'addedAt' => $now],
            plexMovie('Real Movie', 302, $now),
        ]),
        [
            "{$server->uri}/library/metadata/302" => Http::response(
                fakePlexMetadata(302, ['tmdb://55555'])
            ),
        ],
    ));

    $this->artisan('plex:poll-library')->assertSuccessful();

    Notification::assertSentOnDemand(
        PlexLibraryNotification::class,
        function (PlexLibraryNotification $notification) {
            return $notification->items->count() === 1
                && $notification->items->first()['title'] === 'Real Movie';
        }
    );
});

it('skips episodes with null season or episode number', function () {
    $server = createPollableServer();
    $show = Show::factory()->create(['tmdb_id' => 1396]);
    $episode = Episode::factory()->create(['show_id' => $show->id, 'season' => 1, 'number' => 1]);
    $requestItem = RequestItem::factory()->pending()->forRequestable($episode)->create();

    $cid = $server->client_identifier;
    $pastTime = now()->subMinutes(10)->timestamp;

    Cache::put("plex:poll:pending-index:{$cid}", ['50'], 1800);
    Cache::put("plex:poll:pending:{$cid}:50", [
        'server_name' => $server->name,
        'show_title' => 'Breaking Bad',
        'first_seen_at' => $pastTime,
        'last_seen_at' => $pastTime,
        'items' => [
            '200' => [
                'media_type' => 'episode',
                'title' => 'Pilot',
                'show_title' => 'Breaking Bad',
                'season' => null,
                'episode_number' => 1,
                'rating_key' => '200',
                'grandparent_rating_key' => '50',
                'added_at' => $pastTime,
            ],
        ],
    ], 1800);

    Http::fake(array_merge(
        fakeSectionRoutes($server->uri),
        [
            "{$server->uri}/library/metadata/200" => Http::response(
                fakePlexMetadata(200, ['tmdb://1396'])
            ),
        ],
    ));

    $this->artisan('plex:poll-library')->assertSuccessful();

    expect($requestItem->fresh()->status)->toBe(RequestItemStatus::Pending);
});
