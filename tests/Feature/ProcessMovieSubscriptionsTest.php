<?php

use App\Events\RequestSubmitted;
use App\Models\Movie;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('creates a request for a user subscribed to a movie with digital release today', function () {
    Event::fake([RequestSubmitted::class]);

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

    expect(Request::count())->toBe(1);
    expect(Request::first()->user_id)->toBe($user->id);
    expect(RequestItem::count())->toBe(1);
    expect(RequestItem::first()->requestable_id)->toBe($movie->id);

    Event::assertDispatched(RequestSubmitted::class);
});

it('does not create a request for a movie with only theatrical release today', function () {
    Event::fake([RequestSubmitted::class]);

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

    expect(Request::count())->toBe(0);

    Event::assertNotDispatched(RequestSubmitted::class);
});

it('does not create a request for movies not releasing today', function () {
    Event::fake([RequestSubmitted::class]);

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

    expect(Request::count())->toBe(0);

    Event::assertNotDispatched(RequestSubmitted::class);
});

it('does not create a request for unreleased movies even if date matches', function () {
    Event::fake([RequestSubmitted::class]);

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

    expect(Request::count())->toBe(0);

    Event::assertNotDispatched(RequestSubmitted::class);
});

it('does not create a request for unsubscribed movies', function () {
    Event::fake([RequestSubmitted::class]);

    Movie::factory()->create([
        'title' => 'Nobody Cares',
        'year' => 2024,
        'digital_release_date' => today(),
        'status' => 'Released',
    ]);

    $this->artisan('process:movie-subscriptions')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 0 movie subscription(s)');

    expect(Request::count())->toBe(0);

    Event::assertNotDispatched(RequestSubmitted::class);
});

it('creates separate requests for each subscribed user', function () {
    Event::fake([RequestSubmitted::class]);

    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $movie = Movie::factory()->create([
        'digital_release_date' => today(),
        'status' => 'Released',
    ]);

    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $userA->id]);
    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $userB->id]);

    $this->artisan('process:movie-subscriptions')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 2 movie subscription(s)');

    expect(Request::count())->toBe(2);

    Event::assertDispatchedTimes(RequestSubmitted::class, 2);
});
