<?php

use App\Events\SubscriptionTriggered;
use App\Models\Movie;
use App\Models\Request;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Freeze to noon Pacific so UTC and Pacific dates align for `today()` fixtures.
    $this->travelTo(Carbon::parse('2026-05-23 12:00', 'America/Los_Angeles')->utc());
});

it('dispatches a release notification for a subscribed movie digitally released today', function () {
    Event::fake([SubscriptionTriggered::class]);

    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'title' => 'Dune: Part Two',
        'year' => 2024,
        'digital_release_date' => today(),
        'status' => 'Released',
    ]);

    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    $this->artisan('process:movie-subscriptions')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 1 movie subscription(s)');

    expect(Request::count())->toBe(0);

    Event::assertDispatched(SubscriptionTriggered::class);
});

it('still notifies notification-only subscriptions', function () {
    Event::fake([SubscriptionTriggered::class]);

    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'title' => 'Quiet Release',
        'year' => 2024,
        'digital_release_date' => today(),
        'status' => 'Released',
    ]);

    Subscription::factory()->forSubscribable($movie)->notifyOnly()->create(['user_id' => $user->id]);

    $this->artisan('process:movie-subscriptions')->assertSuccessful();

    Event::assertDispatched(SubscriptionTriggered::class);
});

it('does not dispatch for a movie with only theatrical release today', function () {
    Event::fake([SubscriptionTriggered::class]);

    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'title' => 'Oppenheimer',
        'year' => 2023,
        'release_date' => today(),
        'digital_release_date' => null,
        'status' => 'Released',
    ]);

    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    $this->artisan('process:movie-subscriptions')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 0 movie subscription(s)');

    Event::assertNotDispatched(SubscriptionTriggered::class);
});

it('does not dispatch for movies not releasing today', function () {
    Event::fake([SubscriptionTriggered::class]);

    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'title' => 'Future Movie',
        'year' => 2025,
        'digital_release_date' => today()->addDays(30),
        'status' => 'Released',
    ]);

    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    $this->artisan('process:movie-subscriptions')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 0 movie subscription(s)');

    Event::assertNotDispatched(SubscriptionTriggered::class);
});

it('does not dispatch for unreleased movies even if date matches', function () {
    Event::fake([SubscriptionTriggered::class]);

    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'title' => 'Upcoming Film',
        'year' => 2025,
        'release_date' => today(),
        'status' => 'In Production',
    ]);

    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    $this->artisan('process:movie-subscriptions')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 0 movie subscription(s)');

    Event::assertNotDispatched(SubscriptionTriggered::class);
});

it('does not dispatch for unsubscribed movies', function () {
    Event::fake([SubscriptionTriggered::class]);

    Movie::factory()->create([
        'title' => 'Nobody Cares',
        'year' => 2024,
        'digital_release_date' => today(),
        'status' => 'Released',
    ]);

    $this->artisan('process:movie-subscriptions')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 0 movie subscription(s)');

    Event::assertNotDispatched(SubscriptionTriggered::class);
});

it('does not dispatch again on subsequent runs for already-fulfilled subscriptions', function () {
    Event::fake([SubscriptionTriggered::class]);

    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'digital_release_date' => today(),
        'status' => 'Released',
    ]);

    Subscription::factory()->forSubscribable($movie)->create([
        'user_id' => $user->id,
        'fulfilled_at' => now()->subMinutes(10),
    ]);

    $this->artisan('process:movie-subscriptions')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 0 movie subscription(s)');

    Event::assertNotDispatched(SubscriptionTriggered::class);
});

it('does not mark subscriptions as fulfilled so the availability command can still process them', function () {
    Event::fake([SubscriptionTriggered::class]);

    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'digital_release_date' => today(),
        'status' => 'Released',
    ]);

    $subscription = Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    $this->artisan('process:movie-subscriptions')->assertSuccessful();

    expect($subscription->fresh())
        ->fulfilled_at->toBeNull()
        ->notified_at->not->toBeNull();
});

it('does not dispatch again on subsequent runs for already-notified subscriptions', function () {
    Event::fake([SubscriptionTriggered::class]);

    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'digital_release_date' => today(),
        'status' => 'Released',
    ]);

    Subscription::factory()->forSubscribable($movie)->create([
        'user_id' => $user->id,
        'notified_at' => now()->subMinutes(10),
    ]);

    $this->artisan('process:movie-subscriptions')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 0 movie subscription(s)');

    Event::assertNotDispatched(SubscriptionTriggered::class);
});

it('does not dispatch before midnight Pacific on the release date', function () {
    Event::fake([SubscriptionTriggered::class]);

    // 11pm Pacific on May 22 — Pacific date is still May 22, but UTC is already May 23.
    $this->travelTo(Carbon::parse('2026-05-22 23:00', 'America/Los_Angeles')->utc());

    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'digital_release_date' => '2026-05-23',
        'status' => 'Released',
    ]);

    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    $this->artisan('process:movie-subscriptions')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 0 movie subscription(s)');

    Event::assertNotDispatched(SubscriptionTriggered::class);
});

it('dispatches at midnight Pacific on the release date', function () {
    Event::fake([SubscriptionTriggered::class]);

    // Midnight Pacific on May 23.
    $this->travelTo(Carbon::parse('2026-05-23 00:00', 'America/Los_Angeles')->utc());

    $user = User::factory()->create();
    $movie = Movie::factory()->create([
        'digital_release_date' => '2026-05-23',
        'status' => 'Released',
    ]);

    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);

    $this->artisan('process:movie-subscriptions')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 1 movie subscription(s)');

    Event::assertDispatched(SubscriptionTriggered::class);
});

it('dispatches only once when multiple users are subscribed to the same movie', function () {
    Event::fake([SubscriptionTriggered::class]);

    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $movie = Movie::factory()->create([
        'digital_release_date' => today(),
        'status' => 'Released',
    ]);

    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $userA->id]);
    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $userB->id]);

    $this->artisan('process:movie-subscriptions')
        ->assertSuccessful();

    expect(Request::count())->toBe(0);

    Event::assertDispatchedTimes(SubscriptionTriggered::class, 1);
});
