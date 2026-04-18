<?php

use App\Events\MediaAvailable;
use App\Models\Episode;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Show;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    RateLimiter::clear('predb-api');
});

function fakeShowPredb(array $releaseNames): void
{
    $data = array_map(fn (string $name, int $i) => [
        'id' => $i + 1,
        'pretime' => now()->timestamp,
        'release' => $name,
        'section' => 'X264',
        'files' => 1,
        'size' => 1.0,
        'status' => 0,
        'reason' => '',
        'group' => 'GROUP',
        'genre' => '',
        'url' => '/rls/'.$name,
    ], $releaseNames, array_keys($releaseNames));

    Http::fake([
        'api.predb.net*' => Http::response([
            'status' => 'success',
            'message' => '',
            'data' => $data,
            'results' => count($data),
            'time' => 0.05,
        ]),
    ]);
}

it('creates a request for episodes matching available S##E## releases', function () {
    Event::fake([MediaAvailable::class]);

    fakeShowPredb([
        'Severance.S02E01.1080p.WEB-DL.x264-GROUP',
    ]);

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Severance']);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 2,
        'number' => 1,
        'airdate' => today('America/New_York'),
        'airtime' => now('America/New_York')->subHours(2)->format('H:i'),
    ]);
    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 2,
        'number' => 2,
        'airdate' => today('America/New_York'),
        'airtime' => now('America/New_York')->subHours(2)->format('H:i'),
    ]);

    $this->artisan('process:show-availability')->assertSuccessful();

    expect(Request::count())->toBe(1);
    expect(RequestItem::count())->toBe(1);

    Event::assertDispatched(MediaAvailable::class);
});

it('does not request an episode already in subscription_episode', function () {
    Event::fake([MediaAvailable::class]);

    fakeShowPredb([
        'Lost.S01E01.1080p.WEB-DL.x264-GROUP',
    ]);

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Lost']);
    $sub = Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => today(),
        'airtime' => now()->subHours(2)->format('H:i'),
    ]);

    DB::table('subscription_episode')->insert([
        'subscription_id' => $sub->id,
        'episode_id' => $episode->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('process:show-availability')->assertSuccessful();

    expect(Request::count())->toBe(0);
    Http::assertNothingSent();

    Event::assertNotDispatched(MediaAvailable::class);
});

it('skips episodes that aired more than 24 hours ago', function () {
    Event::fake([MediaAvailable::class]);
    Http::fake();

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Old Show']);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => today()->subDays(3),
        'airtime' => '20:00',
    ]);

    $this->artisan('process:show-availability')->assertSuccessful();

    Http::assertNothingSent();
    Event::assertNotDispatched(MediaAvailable::class);
});

it('dedupes API calls across multiple subscriptions on the same show', function () {
    Event::fake([MediaAvailable::class]);

    fakeShowPredb([
        'Game.Of.Thrones.S08E01.1080p.WEB-DL.x264-GROUP',
    ]);

    $show = Show::factory()->create(['name' => 'Game Of Thrones']);

    foreach (range(1, 3) as $_) {
        Subscription::factory()->forSubscribable($show)->create([
            'user_id' => User::factory()->create()->id,
        ]);
    }

    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 8,
        'number' => 1,
        'airdate' => today('America/New_York'),
        'airtime' => now('America/New_York')->subHours(2)->format('H:i'),
    ]);

    $this->artisan('process:show-availability')->assertSuccessful();

    Http::assertSentCount(1);
    expect(Request::count())->toBe(3);

    Event::assertDispatchedTimes(MediaAvailable::class, 1);
});

it('marks newly requested episodes in the pivot table', function () {
    Event::fake([MediaAvailable::class]);

    fakeShowPredb([
        'The.Wire.S01E01.1080p.WEB-DL.x264-GROUP',
    ]);

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'The Wire']);
    $sub = Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => today('America/New_York'),
        'airtime' => now('America/New_York')->subHours(2)->format('H:i'),
    ]);

    $this->artisan('process:show-availability')->assertSuccessful();

    expect($sub->fresh()->processedEpisodes->pluck('id')->all())->toBe([$episode->id]);
});

it('bails early when the PreDB rate limit is reached', function () {
    Event::fake([MediaAvailable::class]);
    Http::fake();

    foreach (range(1, 28) as $_) {
        RateLimiter::hit('predb-api', 60);
    }

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Whatever']);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => today(),
        'airtime' => now()->subHours(2)->format('H:i'),
    ]);

    $this->artisan('process:show-availability')->assertSuccessful();

    Http::assertNothingSent();
    Event::assertNotDispatched(MediaAvailable::class);
});
