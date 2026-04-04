<?php

use App\Models\Movie;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the new user greeting when the user has no requests', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertSee(__('lundbergh.dashboard.greeting_new'), false);
});

it('shows the review line for a returning user', function () {
    $user = User::factory()->create();
    $request = Request::factory()->for($user)->create();
    $movie = Movie::factory()->create();
    RequestItem::factory()->forRequestable($movie)->pending()->create([
        'request_id' => $request->id,
    ]);

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertSee(__('lundbergh.dashboard.review_requests'), false);
});

it('shows the review line when all request items were rejected', function () {
    $user = User::factory()->create();
    $request = Request::factory()->for($user)->create();
    $movie = Movie::factory()->create();
    RequestItem::factory()->forRequestable($movie)->rejected($user->id)->create([
        'request_id' => $request->id,
    ]);

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertDontSee(__('lundbergh.dashboard.greeting_new'), false)
        ->assertSee(__('lundbergh.dashboard.review_requests'), false);
});

it('shows the fulfilled line when items were fulfilled today', function () {
    $user = User::factory()->create();
    $request = Request::factory()->for($user)->create();

    $movie = Movie::factory()->create();
    RequestItem::factory()->forRequestable($movie)->fulfilled($user->id)->create([
        'request_id' => $request->id,
        'actioned_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertSee(trans_choice('lundbergh.dashboard.last_fulfilled', 1, [
            'count' => 1,
            'when' => __('lundbergh.dashboard.when_today'),
        ]), false);
});

it('groups fulfilled items by the user timezone, not UTC', function () {
    // Freeze time: Apr 3 05:00 UTC = Apr 2 22:00 PDT
    $this->travelTo(Carbon::parse('2026-04-03 05:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'America/Los_Angeles']);
    $request = Request::factory()->for($user)->create();

    $movie = Movie::factory()->create();
    // Fulfilled at 1am UTC on Apr 3 — which is 6pm PDT on Apr 2
    RequestItem::factory()->forRequestable($movie)->fulfilled($user->id)->create([
        'request_id' => $request->id,
        'actioned_at' => '2026-04-03 01:00:00',
    ]);

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertSee(trans_choice('lundbergh.dashboard.last_fulfilled', 1, [
            'count' => 1,
            'when' => __('lundbergh.dashboard.when_today'),
        ]), false);
});

it('shows the fulfilled line with yesterday', function () {
    $user = User::factory()->create();
    $request = Request::factory()->for($user)->create();

    $movie1 = Movie::factory()->create();
    $movie2 = Movie::factory()->create();
    RequestItem::factory()->forRequestable($movie1)->fulfilled($user->id)->create([
        'request_id' => $request->id,
        'actioned_at' => now()->subDay(),
    ]);
    RequestItem::factory()->forRequestable($movie2)->fulfilled($user->id)->create([
        'request_id' => $request->id,
        'actioned_at' => now()->subDay(),
    ]);

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertSee(trans_choice('lundbergh.dashboard.last_fulfilled', 2, [
            'count' => 2,
            'when' => __('lundbergh.dashboard.when_yesterday'),
        ]), false);
});

it('shows the fulfilled line with days ago', function () {
    $user = User::factory()->create();
    $request = Request::factory()->for($user)->create();

    $movie = Movie::factory()->create();
    RequestItem::factory()->forRequestable($movie)->fulfilled($user->id)->create([
        'request_id' => $request->id,
        'actioned_at' => now()->subDays(5),
    ]);

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertSee(trans_choice('lundbergh.dashboard.last_fulfilled', 1, [
            'count' => 1,
            'when' => trans_choice('lundbergh.dashboard.when_days_ago', 5, ['count' => 5]),
        ]), false);
});

it('shows the pending line when items are pending', function () {
    $user = User::factory()->create();
    $request = Request::factory()->for($user)->create();

    for ($i = 0; $i < 3; $i++) {
        $movie = Movie::factory()->create();
        RequestItem::factory()->forRequestable($movie)->pending()->create([
            'request_id' => $request->id,
        ]);
    }

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertSee(trans_choice('lundbergh.dashboard.pending', 3, ['count' => 3]), false);
});

it('shows both fulfilled and pending lines together', function () {
    $user = User::factory()->create();
    $request = Request::factory()->for($user)->create();

    $fulfilled = Movie::factory()->create();
    RequestItem::factory()->forRequestable($fulfilled)->fulfilled($user->id)->create([
        'request_id' => $request->id,
        'actioned_at' => now(),
    ]);

    $pending = Movie::factory()->create();
    RequestItem::factory()->forRequestable($pending)->pending()->create([
        'request_id' => $request->id,
    ]);

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertSee(trans_choice('lundbergh.dashboard.last_fulfilled', 1, [
            'count' => 1,
            'when' => __('lundbergh.dashboard.when_today'),
        ]), false)
        ->assertSee(trans_choice('lundbergh.dashboard.pending', 1, ['count' => 1]), false)
        ->assertSee(__('lundbergh.dashboard.review_requests'), false);
});
