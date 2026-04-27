<?php

use App\Models\Movie;
use App\Models\Request;
use App\Models\RequestItem;
use App\Notifications\RequestProcessedNotification;
use Illuminate\Notifications\AnonymousNotifiable;

it('fits small requests in a single section block', function () {
    $request = Request::factory()->create();
    $movie = Movie::factory()->create(['title' => 'Inception', 'year' => 2010]);
    RequestItem::factory()->forRequestable($movie)->fulfilled()->create(['request_id' => $request->id]);

    $notification = new RequestProcessedNotification($request->load('items.requestable'));
    $blocks = $notification->toSlack(new AnonymousNotifiable)->toArray()['blocks'];

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]['text']['text'])->toContain('*📤 Request Processed*')
        ->and($blocks[0]['text']['text'])->toContain('Inception (2010)');
});

it('splits large requests across multiple section blocks', function () {
    $request = Request::factory()->create();

    $movies = Movie::factory()->count(100)->sequence(
        fn ($seq) => ['title' => 'Movie With A Reasonably Long Title Number '.str_pad((string) ($seq->index + 1), 3, '0', STR_PAD_LEFT), 'year' => 2020 + ($seq->index % 5)],
    )->create();

    foreach ($movies as $movie) {
        RequestItem::factory()->forRequestable($movie)->fulfilled()->create(['request_id' => $request->id]);
    }

    $notification = new RequestProcessedNotification($request->load('items.requestable'));
    $blocks = $notification->toSlack(new AnonymousNotifiable)->toArray()['blocks'];

    expect(count($blocks))->toBeGreaterThan(1);

    foreach ($blocks as $block) {
        expect(mb_strlen($block['text']['text']))->toBeLessThanOrEqual(3000);
    }

    expect($blocks[0]['text']['text'])->toStartWith('*📤 Request Processed*');
});

it('does not repeat the heading in continuation blocks', function () {
    $request = Request::factory()->create();

    $movies = Movie::factory()->count(100)->sequence(
        fn ($seq) => ['title' => 'Movie With A Reasonably Long Title Number '.str_pad((string) ($seq->index + 1), 3, '0', STR_PAD_LEFT), 'year' => 2020],
    )->create();

    foreach ($movies as $movie) {
        RequestItem::factory()->forRequestable($movie)->fulfilled()->create(['request_id' => $request->id]);
    }

    $notification = new RequestProcessedNotification($request->load('items.requestable'));
    $blocks = $notification->toSlack(new AnonymousNotifiable)->toArray()['blocks'];

    expect(count($blocks))->toBeGreaterThan(1);

    foreach (array_slice($blocks, 1) as $block) {
        expect($block['text']['text'])->not->toContain('Request Processed');
    }
});

it('handles mixed statuses across multiple blocks', function () {
    $request = Request::factory()->create();

    $fulfilledMovies = Movie::factory()->count(40)->sequence(
        fn ($seq) => ['title' => 'Fulfilled Movie '.str_pad((string) ($seq->index + 1), 3, '0', STR_PAD_LEFT), 'year' => 2020],
    )->create();

    foreach ($fulfilledMovies as $movie) {
        RequestItem::factory()->forRequestable($movie)->fulfilled()->create(['request_id' => $request->id]);
    }

    $rejectedMovies = Movie::factory()->count(40)->sequence(
        fn ($seq) => ['title' => 'Rejected Movie '.str_pad((string) ($seq->index + 1), 3, '0', STR_PAD_LEFT), 'year' => 2021],
    )->create();

    foreach ($rejectedMovies as $movie) {
        RequestItem::factory()->forRequestable($movie)->rejected()->create(['request_id' => $request->id]);
    }

    $notification = new RequestProcessedNotification($request->load('items.requestable'));
    $blocks = $notification->toSlack(new AnonymousNotifiable)->toArray()['blocks'];

    $allText = implode("\n", array_column(array_column($blocks, 'text'), 'text'));

    expect($allText)->toContain('*Fulfilled:*')
        ->and($allText)->toContain('*Rejected:*');

    foreach ($blocks as $block) {
        expect(mb_strlen($block['text']['text']))->toBeLessThanOrEqual(3000);
    }
});
