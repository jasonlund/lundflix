<?php

use App\Events\SubscriptionTriggered;
use App\Models\Episode;
use App\Models\Request;
use App\Models\Show;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('dispatches a release notification for episodes airing within the 15-minute window', function () {
    Event::fake([SubscriptionTriggered::class]);

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Stranger Things']);

    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 6,
        'number' => 1,
        'airdate' => today('America/New_York'),
        'airtime' => now('America/New_York')->subMinutes(5)->format('H:i'),
    ]);

    $this->artisan('process:show-subscriptions')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 1 show subscription(s)');

    expect(Request::count())->toBe(0);

    Event::assertDispatched(SubscriptionTriggered::class);
});

it('does not dispatch for episodes outside the 15-minute window', function () {
    Event::fake([SubscriptionTriggered::class]);

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Breaking Bad']);

    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 5,
        'number' => 1,
        'airdate' => today(),
        'airtime' => now()->subMinutes(30)->format('H:i'),
    ]);

    $this->artisan('process:show-subscriptions')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 0 show subscription(s)');

    Event::assertNotDispatched(SubscriptionTriggered::class);
});

it('treats null airtime as midnight', function () {
    Event::fake([SubscriptionTriggered::class]);

    $this->travelTo(today('America/New_York')->addMinutes(10));

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'The Wire']);

    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => today('America/New_York'),
        'airtime' => null,
    ]);

    $this->artisan('process:show-subscriptions')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 1 show subscription(s)');

    Event::assertDispatched(SubscriptionTriggered::class);
});

it('does not dispatch for unsubscribed shows', function () {
    Event::fake([SubscriptionTriggered::class]);

    $show = Show::factory()->create(['name' => 'Dexter']);

    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => today(),
        'airtime' => now()->subMinutes(5)->format('H:i'),
    ]);

    $this->artisan('process:show-subscriptions')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 0 show subscription(s)');

    Event::assertNotDispatched(SubscriptionTriggered::class);
});

it('dispatches only once when multiple users are subscribed to the same show', function () {
    Event::fake([SubscriptionTriggered::class]);

    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Game of Thrones']);

    Subscription::factory()->forSubscribable($show)->create(['user_id' => $userA->id]);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $userB->id]);

    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => today('America/New_York'),
        'airtime' => now('America/New_York')->subMinutes(5)->format('H:i'),
    ]);

    $this->artisan('process:show-subscriptions')
        ->assertSuccessful();

    expect(Request::count())->toBe(0);

    Event::assertDispatchedTimes(SubscriptionTriggered::class, 1);
});

it('does not dispatch again for episodes already processed in a prior run', function () {
    Event::fake([SubscriptionTriggered::class]);

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'The Expanse']);

    $subscription = Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 6,
        'number' => 1,
        'airdate' => today('America/New_York'),
        'airtime' => now('America/New_York')->subMinutes(5)->format('H:i'),
    ]);

    $subscription->processedEpisodes()->attach($episode->id);

    $this->artisan('process:show-subscriptions')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 0 show subscription(s)');

    Event::assertNotDispatched(SubscriptionTriggered::class);
});

it('records processed episodes in the pivot after dispatching', function () {
    Event::fake([SubscriptionTriggered::class]);

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Succession']);

    $subscription = Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 4,
        'number' => 1,
        'airdate' => today('America/New_York'),
        'airtime' => now('America/New_York')->subMinutes(5)->format('H:i'),
    ]);

    $this->artisan('process:show-subscriptions')->assertSuccessful();

    expect($subscription->processedEpisodes()->pluck('episodes.id')->toArray())
        ->toContain($episode->id);
});

it('skips fulfilled show subscriptions', function () {
    Event::fake([SubscriptionTriggered::class]);

    $user = User::factory()->create();
    $show = Show::factory()->create();

    Subscription::factory()->forSubscribable($show)->create([
        'user_id' => $user->id,
        'fulfilled_at' => now()->subDay(),
    ]);

    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => today('America/New_York'),
        'airtime' => now('America/New_York')->subMinutes(5)->format('H:i'),
    ]);

    $this->artisan('process:show-subscriptions')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 0 show subscription(s)');

    Event::assertNotDispatched(SubscriptionTriggered::class);
});

it('does not duplicate dispatches for episodes on the window boundary', function () {
    Event::fake([SubscriptionTriggered::class]);

    // Simulate the 20:15 run — an episode that aired at 20:00 should NOT match
    // because it was already processed by the 20:00 run
    $this->travelTo(today()->setTime(20, 15));

    $user = User::factory()->create();
    $show = Show::factory()->create();

    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => today(),
        'airtime' => '20:00',
    ]);

    $this->artisan('process:show-subscriptions')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 0 show subscription(s)');

    Event::assertNotDispatched(SubscriptionTriggered::class);
});

it('processes Apple TV+ episodes that air at 6 PM Pacific the day before the listed date', function () {
    Event::fake([SubscriptionTriggered::class]);

    // Travel to 2026-04-02 18:05 PDT (just after the 6 PM Apple TV+ drop)
    $this->travelTo(Carbon::parse('2026-04-02 18:05', 'America/Los_Angeles')->utc());

    $user = User::factory()->create();
    $show = Show::factory()->appleTvPlus()->create(['name' => 'For All Mankind']);

    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 5,
        'number' => 1,
        'airdate' => '2026-04-03',
        'airtime' => null,
    ]);

    $this->artisan('process:show-subscriptions')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 1 show subscription(s)');

    Event::assertDispatched(SubscriptionTriggered::class);
});

it('skips deleted shows without crashing', function () {
    Event::fake([SubscriptionTriggered::class]);

    $user = User::factory()->create();
    $show = Show::factory()->create();

    $subscription = Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $show->forceDelete();

    $this->artisan('process:show-subscriptions')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 0 show subscription(s)');

    Event::assertNotDispatched(SubscriptionTriggered::class);
});

it('does not process Apple TV+ episodes before 6 PM Pacific the day before', function () {
    Event::fake([SubscriptionTriggered::class]);

    // Travel to 2026-04-02 17:50 PDT (before the 6 PM Apple TV+ drop)
    $this->travelTo(Carbon::parse('2026-04-02 17:50', 'America/Los_Angeles')->utc());

    $user = User::factory()->create();
    $show = Show::factory()->appleTvPlus()->create(['name' => 'Severance']);

    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 2,
        'number' => 1,
        'airdate' => '2026-04-03',
        'airtime' => null,
    ]);

    $this->artisan('process:show-subscriptions')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 0 show subscription(s)');

    Event::assertNotDispatched(SubscriptionTriggered::class);
});

it('includes all new episodes in the notification when subscribers have different processed states', function () {
    Event::fake([SubscriptionTriggered::class]);

    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $show = Show::factory()->create(['name' => 'The Bear']);

    $subscriptionA = Subscription::factory()->forSubscribable($show)->create(['user_id' => $userA->id]);
    $subscriptionB = Subscription::factory()->forSubscribable($show)->create(['user_id' => $userB->id]);

    $episode1 = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 3,
        'number' => 1,
        'airdate' => today('America/New_York'),
        'airtime' => now('America/New_York')->subMinutes(5)->format('H:i'),
    ]);

    $episode2 = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 3,
        'number' => 2,
        'airdate' => today('America/New_York'),
        'airtime' => now('America/New_York')->subMinutes(5)->format('H:i'),
    ]);

    // User A already processed episode 1 in a prior run
    $subscriptionA->processedEpisodes()->attach($episode1->id);

    $this->artisan('process:show-subscriptions')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 1 show subscription(s)');

    Event::assertDispatchedTimes(SubscriptionTriggered::class, 1);

    Event::assertDispatched(SubscriptionTriggered::class, function (SubscriptionTriggered $event) use ($episode1, $episode2) {
        return $event->episodes->pluck('id')->sort()->values()->all() === [$episode1->id, $episode2->id];
    });
});
