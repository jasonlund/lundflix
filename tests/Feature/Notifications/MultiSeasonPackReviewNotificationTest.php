<?php

use App\Models\Episode;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Show;
use App\Notifications\MultiSeasonPackReviewNotification;

it('renders slack message with pack details', function () {
    $show = Show::factory()->create(['name' => 'The Wire']);
    $episode = Episode::factory()->create(['show_id' => $show->id, 'season' => 3, 'number' => 1]);
    $request = Request::factory()->create();
    $item = RequestItem::factory()->forRequestable($episode)->create(['request_id' => $request->id]);

    $pack = [
        'torrent_id' => 999,
        'name' => 'The.Wire.Complete.Series.1080p.BluRay.x265-RARBG',
        'size' => '84.10 GB',
        'seeders' => 5,
        'leechers' => 1,
        'snatches' => 100,
        'uploaded' => 'yesterday',
        'download_url' => 'https://iptorrents.com/download.php/999/wire.torrent',
    ];

    $notification = new MultiSeasonPackReviewNotification([
        [
            'show' => $show,
            'season' => 3,
            'pack' => $pack,
            'requestItems' => [$item],
        ],
    ]);

    $payload = $notification->toSlack(new stdClass)->toArray();
    $text = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    expect($text)->toContain('Multi-season pack detected')
        ->and($text)->toContain('The Wire')
        ->and($text)->toContain('Season 3')
        ->and($text)->toContain('The.Wire.Complete.Series')
        ->and($text)->toContain('iptorrents.com/download.php/999');
});
