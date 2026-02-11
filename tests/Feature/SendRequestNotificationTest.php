<?php

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Show;
use App\Notifications\RequestItemsNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.slack.enabled' => true, 'services.slack.notifications.channel' => 'U12345']);
});

it('sends a slack notification for a request with movies', function () {
    Notification::fake();

    $request = Request::factory()->create();
    $movie = Movie::factory()->create(['title' => 'Inception', 'year' => 2010]);
    RequestItem::factory()->forRequestable($movie)->create(['request_id' => $request->id]);

    $this->artisan('slack:send-request', ['request' => $request->id])
        ->assertSuccessful()
        ->expectsOutputToContain("Notification sent for request #{$request->id}");

    Notification::assertSentOnDemand(RequestItemsNotification::class);
});

it('sends a slack notification for a request with episodes', function () {
    Notification::fake();

    $show = Show::factory()->create(['name' => 'Breaking Bad']);
    $request = Request::factory()->create();

    $episodes = Episode::factory()->count(3)->sequence(
        ['number' => 1, 'airdate' => '2024-01-01'],
        ['number' => 2, 'airdate' => '2024-01-08'],
        ['number' => 3, 'airdate' => '2024-01-15'],
    )->create([
        'show_id' => $show->id,
        'season' => 1,
        'type' => 'regular',
    ]);

    foreach ($episodes as $episode) {
        RequestItem::factory()->forRequestable($episode)->create(['request_id' => $request->id]);
    }

    $this->artisan('slack:send-request', ['request' => $request->id])
        ->assertSuccessful();

    Notification::assertSentOnDemand(RequestItemsNotification::class);
});

it('sends a slack notification for mixed movies and episodes', function () {
    Notification::fake();

    $request = Request::factory()->create();

    $movie = Movie::factory()->create(['title' => 'The Matrix', 'year' => 1999]);
    RequestItem::factory()->forRequestable($movie)->create(['request_id' => $request->id]);

    $show = Show::factory()->create(['name' => 'Lost']);
    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 2,
        'number' => 5,
        'type' => 'regular',
    ]);
    RequestItem::factory()->forRequestable($episode)->create(['request_id' => $request->id]);

    $this->artisan('slack:send-request', ['request' => $request->id])
        ->assertSuccessful();

    Notification::assertSentOnDemand(RequestItemsNotification::class);
});

it('fails when request does not exist', function () {
    $this->artisan('slack:send-request', ['request' => 99999])
        ->assertFailed()
        ->expectsOutputToContain('Request not found');
});

it('fails when request has no items', function () {
    $request = Request::factory()->create();

    $this->artisan('slack:send-request', ['request' => $request->id])
        ->assertFailed()
        ->expectsOutputToContain('Request has no items');
});

it('fails when slack is not enabled', function () {
    config(['services.slack.enabled' => false]);

    $this->artisan('slack:send-request', ['request' => 1])
        ->assertFailed()
        ->expectsOutputToContain('Slack is not enabled');
});

it('fails when slack channel is not configured', function () {
    config(['services.slack.notifications.channel' => null]);

    $request = Request::factory()->create();
    $movie = Movie::factory()->create();
    RequestItem::factory()->forRequestable($movie)->create(['request_id' => $request->id]);

    $this->artisan('slack:send-request', ['request' => $request->id])
        ->assertFailed()
        ->expectsOutputToContain('Slack channel not configured');
});
