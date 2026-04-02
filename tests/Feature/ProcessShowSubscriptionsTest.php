<?php

use App\Events\RequestSubmitted;
use App\Models\Episode;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Show;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('creates a request for episodes airing within the 15-minute window', function () {
    Event::fake([RequestSubmitted::class]);

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Stranger Things']);

    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 6,
        'number' => 1,
        'airdate' => today(),
        'airtime' => now()->subMinutes(5)->format('H:i'),
    ]);

    $this->artisan('process:show-subscriptions')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 1 show subscription(s)');

    expect(Request::count())->toBe(1);
    expect(Request::first()->user_id)->toBe($user->id);
    expect(RequestItem::count())->toBe(1);

    Event::assertDispatched(RequestSubmitted::class);
});

it('does not create a request for episodes outside the 15-minute window', function () {
    Event::fake([RequestSubmitted::class]);

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

    expect(Request::count())->toBe(0);

    Event::assertNotDispatched(RequestSubmitted::class);
});

it('groups multiple episodes into a single request per show', function () {
    Event::fake([RequestSubmitted::class]);

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Lost']);

    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    $airtime = now()->subMinutes(5)->format('H:i');

    Episode::factory()->count(3)->sequence(
        ['number' => 1],
        ['number' => 2],
        ['number' => 3],
    )->create([
        'show_id' => $show->id,
        'season' => 1,
        'airdate' => today(),
        'airtime' => $airtime,
    ]);

    $this->artisan('process:show-subscriptions')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 1 show subscription(s)');

    expect(Request::count())->toBe(1);
    expect(RequestItem::count())->toBe(3);

    Event::assertDispatchedTimes(RequestSubmitted::class, 1);
});

it('treats null airtime as midnight', function () {
    Event::fake([RequestSubmitted::class]);

    $this->travelTo(today()->addMinutes(10));

    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'The Wire']);

    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => today(),
        'airtime' => null,
    ]);

    $this->artisan('process:show-subscriptions')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 1 show subscription(s)');

    expect(Request::count())->toBe(1);

    Event::assertDispatched(RequestSubmitted::class);
});

it('does not create a request for unsubscribed shows', function () {
    Event::fake([RequestSubmitted::class]);

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

    expect(Request::count())->toBe(0);

    Event::assertNotDispatched(RequestSubmitted::class);
});

it('creates separate requests for each subscribed user', function () {
    Event::fake([RequestSubmitted::class]);

    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Game of Thrones']);

    Subscription::factory()->forSubscribable($show)->create(['user_id' => $userA->id]);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $userB->id]);

    Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 1,
        'airdate' => today(),
        'airtime' => now()->subMinutes(5)->format('H:i'),
    ]);

    $this->artisan('process:show-subscriptions')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 2 show subscription(s)');

    expect(Request::count())->toBe(2);

    Event::assertDispatchedTimes(RequestSubmitted::class, 2);
});

it('does not duplicate requests for episodes on the window boundary', function () {
    Event::fake([RequestSubmitted::class]);

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

    expect(Request::count())->toBe(0);

    Event::assertNotDispatched(RequestSubmitted::class);
});
