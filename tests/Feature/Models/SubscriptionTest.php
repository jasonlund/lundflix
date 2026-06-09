<?php

use App\Enums\SubscriptionMode;
use App\Models\Movie;
use App\Models\Show;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('casts mode to the SubscriptionMode enum', function () {
    $subscription = Subscription::factory()->notifyOnly()->create();

    expect($subscription->fresh()->mode)->toBe(SubscriptionMode::Notify);
});

it('defaults mode to download at the database level', function () {
    $movie = Movie::factory()->create();
    $user = User::factory()->create();

    DB::table('subscriptions')->insert([
        'user_id' => $user->id,
        'subscribable_type' => Movie::class,
        'subscribable_id' => $movie->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(Subscription::first()->mode)->toBe(SubscriptionMode::Download);
});

it('only returns download subscriptions from the downloads scope', function () {
    Subscription::factory()->count(2)->create();
    Subscription::factory()->notifyOnly()->create();

    expect(Subscription::query()->downloads()->count())->toBe(2);
});

it('belongs to a user', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->create(['user_id' => $user->id]);

    expect($subscription->user->id)->toBe($user->id);
});

it('morphs to a movie', function () {
    $movie = Movie::factory()->create();
    $subscription = Subscription::factory()->forSubscribable($movie)->create();

    expect($subscription->subscribable)->toBeInstanceOf(Movie::class)
        ->and($subscription->subscribable->id)->toBe($movie->id);
});

it('morphs to a show', function () {
    $show = Show::factory()->create();
    $subscription = Subscription::factory()->forSubscribable($show)->create();

    expect($subscription->subscribable)->toBeInstanceOf(Show::class)
        ->and($subscription->subscribable->id)->toBe($show->id);
});

it('can be accessed from a user', function () {
    $user = User::factory()->create();
    Subscription::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->subscriptions)->toHaveCount(3);
});

it('can be accessed from a movie', function () {
    $movie = Movie::factory()->create();
    Subscription::factory()->count(2)->forSubscribable($movie)->create();

    expect($movie->subscriptions)->toHaveCount(2);
});

it('can be accessed from a show', function () {
    $show = Show::factory()->create();
    Subscription::factory()->count(2)->forSubscribable($show)->create();

    expect($show->subscriptions)->toHaveCount(2);
});

it('prevents duplicate subscriptions for the same user and subscribable', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();

    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);
    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);
})->throws(QueryException::class);

it('allows the same user to subscribe to different movies', function () {
    $user = User::factory()->create();
    $movie1 = Movie::factory()->create();
    $movie2 = Movie::factory()->create();

    Subscription::factory()->forSubscribable($movie1)->create(['user_id' => $user->id]);
    Subscription::factory()->forSubscribable($movie2)->create(['user_id' => $user->id]);

    expect($user->subscriptions)->toHaveCount(2);
});

it('allows different users to subscribe to the same movie', function () {
    $movie = Movie::factory()->create();

    Subscription::factory()->forSubscribable($movie)->count(2)->create();

    expect($movie->subscriptions)->toHaveCount(2);
});

it('deletes subscriptions when the user is deleted', function () {
    $user = User::factory()->create();
    Subscription::factory()->count(3)->create(['user_id' => $user->id]);

    expect(Subscription::where('user_id', $user->id)->count())->toBe(3);

    $user->delete();

    expect(Subscription::where('user_id', $user->id)->count())->toBe(0);
});

it('allows a user to subscribe to both a movie and a show', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    $show = Show::factory()->create();

    Subscription::factory()->forSubscribable($movie)->create(['user_id' => $user->id]);
    Subscription::factory()->forSubscribable($show)->create(['user_id' => $user->id]);

    expect($user->subscriptions)->toHaveCount(2);
});
