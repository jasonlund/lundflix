<?php

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Show;
use App\Notifications\TorrentNotFoundNotification;

it('renders a slack message with movie and episode items', function () {
    $request = Request::factory()->create();
    $movie = Movie::factory()->create(['title' => 'Test Movie', 'year' => 2024]);
    $show = Show::factory()->create(['name' => 'Test Show']);
    $episode = Episode::factory()->create(['show_id' => $show->id, 'season' => 2, 'number' => 7]);

    $movieItem = RequestItem::factory()->forRequestable($movie)->create(['request_id' => $request->id]);
    $episodeItem = RequestItem::factory()->forRequestable($episode)->create(['request_id' => $request->id]);

    $notification = new TorrentNotFoundNotification([$movieItem, $episodeItem]);
    $message = $notification->toSlack(new stdClass);

    $payload = $message->toArray();
    $text = json_encode($payload, JSON_UNESCAPED_UNICODE);

    expect($text)->toContain('Could not find torrents')
        ->and($text)->toContain('Movie — Test Movie (2024)')
        ->and($text)->toContain('Episode — Test Show S02E07');
});

it('uses singular wording when one item', function () {
    $request = Request::factory()->create();
    $movie = Movie::factory()->create();
    $item = RequestItem::factory()->forRequestable($movie)->create(['request_id' => $request->id]);

    $notification = new TorrentNotFoundNotification([$item]);
    $payload = $notification->toSlack(new stdClass)->toArray();
    $text = json_encode($payload, JSON_UNESCAPED_UNICODE);

    expect($text)->toContain('1 item had no matching torrent');
});
