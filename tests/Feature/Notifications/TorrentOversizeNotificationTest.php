<?php

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Show;
use App\Notifications\TorrentOversizeNotification;

it('renders slack message with size cap', function () {
    $request = Request::factory()->create();
    $movie = Movie::factory()->create(['title' => 'Big Movie', 'year' => 2023]);
    $item = RequestItem::factory()->forRequestable($movie)->create(['request_id' => $request->id]);

    $notification = new TorrentOversizeNotification([['item' => $item, 'maxBytes' => 15 * 1024 ** 3]]);
    $payload = $notification->toSlack(new stdClass)->toArray();
    $text = json_encode($payload, JSON_UNESCAPED_UNICODE);

    expect($text)->toContain('Matches found but all exceed size cap')
        ->and($text)->toContain('15.00 GB')
        ->and($text)->toContain('Movie — Big Movie (2023)');
});

it('renders a section per cap for mixed movie and episode oversize', function () {
    $request = Request::factory()->create();
    $movie = Movie::factory()->create(['title' => 'Big Movie', 'year' => 2023]);
    $movieItem = RequestItem::factory()->forRequestable($movie)->create(['request_id' => $request->id]);

    $show = Show::factory()->create();
    $episode = Episode::factory()->create(['show_id' => $show->id]);
    $episodeItem = RequestItem::factory()->forRequestable($episode)->create(['request_id' => $request->id]);

    $notification = new TorrentOversizeNotification([
        ['item' => $movieItem, 'maxBytes' => 15 * 1024 ** 3],
        ['item' => $episodeItem, 'maxBytes' => 4 * 1024 ** 3],
    ]);
    $payload = $notification->toSlack(new stdClass)->toArray();
    $text = json_encode($payload, JSON_UNESCAPED_UNICODE);

    expect($text)->toContain('15.00 GB')
        ->and($text)->toContain('4.00 GB')
        ->and($text)->toContain('Big Movie (2023)');
});
