<?php

use App\Services\IptorrentsService;
use App\Services\Torrent\Support\VerifiedResultPicker;

function makePickerRow(int $id, string $name, string $size): array
{
    return [
        'torrent_id' => $id,
        'name' => $name,
        'size' => $size,
        'seeders' => 10,
        'leechers' => 1,
        'snatches' => 5,
        'uploaded' => 'now',
        'download_url' => "https://iptorrents.com/download.php/{$id}/file.torrent",
    ];
}

it('classifies oversize match even when a fitting candidate with the same prefix was rejected', function () {
    $expectedImdb = 'tt1234567';

    // Both share the prefix "the show" so prefixKey() collides. The oversize row
    // comes first so it lands in the skipped list before the fitting row records
    // the prefix as rejected.
    $results = collect([
        makePickerRow(1, 'The Show S01E01 2160p', '200 GB'), // oversize, correct IMDB
        makePickerRow(2, 'The Show S01E01 1080p', '50 GB'),  // fits cap, wrong IMDB → poisons prefix in old code
    ]);

    $ipt = Mockery::mock(IptorrentsService::class);
    $ipt->shouldReceive('fetchTorrentImdbId')->with(1)->andReturn($expectedImdb);
    $ipt->shouldReceive('fetchTorrentImdbId')->with(2)->andReturn('tt9999999');

    $picker = new VerifiedResultPicker($ipt);

    $result = $picker->pickDetailed($results, $expectedImdb, maxBytes: 100 * 1024 ** 3);

    expect($result->match)->toBeNull()
        ->and($result->oversizeCandidatesExisted)->toBeTrue();
});

it('dedupes by prefix within the oversize pass', function () {
    $expectedImdb = 'tt1234567';

    // Two oversize rows sharing a prefix; only the first should be looked up.
    $results = collect([
        makePickerRow(1, 'The Show S01E01 2160p', '200 GB'),
        makePickerRow(2, 'The Show S01E02 2160p', '210 GB'),
    ]);

    $ipt = Mockery::mock(IptorrentsService::class);
    $ipt->shouldReceive('fetchTorrentImdbId')->with(1)->once()->andReturn('tt9999999');
    $ipt->shouldReceive('fetchTorrentImdbId')->with(2)->never();

    $picker = new VerifiedResultPicker($ipt);

    $result = $picker->pickDetailed($results, $expectedImdb, maxBytes: 100 * 1024 ** 3);

    expect($result->match)->toBeNull()
        ->and($result->oversizeCandidatesExisted)->toBeFalse();
});
