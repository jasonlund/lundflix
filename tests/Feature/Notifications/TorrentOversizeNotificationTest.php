<?php

use App\Models\Movie;
use App\Models\Request;
use App\Models\RequestItem;
use App\Notifications\TorrentOversizeNotification;

it('renders slack message with size cap', function () {
    $request = Request::factory()->create();
    $movie = Movie::factory()->create(['title' => 'Big Movie', 'year' => 2023]);
    $item = RequestItem::factory()->forRequestable($movie)->create(['request_id' => $request->id]);

    $notification = new TorrentOversizeNotification([$item], 15 * 1024 ** 3);
    $payload = $notification->toSlack(new stdClass)->toArray();
    $text = json_encode($payload, JSON_UNESCAPED_UNICODE);

    expect($text)->toContain('Matches found but all exceed size cap')
        ->and($text)->toContain('15.00 GB')
        ->and($text)->toContain('Movie — Big Movie (2023)');
});
