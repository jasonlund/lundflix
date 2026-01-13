<?php

use App\Services\TMDBService;

it('parses ndjson export file correctly', function () {
    // Create a test gzip file with NDJSON content
    $tempFile = tempnam(sys_get_temp_dir(), 'tmdb_test_');
    $gzFile = $tempFile.'.gz';

    $content = implode("\n", [
        '{"id":550,"original_title":"Fight Club","popularity":61.416,"video":false,"adult":false}',
        '{"id":551,"original_title":"Adult Movie","popularity":10.0,"video":false,"adult":true}',
        '{"id":552,"original_title":"The Matrix","popularity":73.462,"video":false,"adult":false}',
        '{"id":553,"original_title":"Some Video Release","popularity":5.0,"video":true,"adult":false}',
    ]);

    $gz = gzopen($gzFile, 'w');
    gzwrite($gz, $content);
    gzclose($gz);

    $service = new TMDBService;
    $movies = iterator_to_array($service->parseExportFile($gzFile));

    // Should skip adult content and video releases
    expect($movies)->toHaveCount(2)
        ->and($movies[0]['original_title'])->toBe('Fight Club')
        ->and($movies[1]['original_title'])->toBe('The Matrix');

    unlink($gzFile);
    @unlink($tempFile);
});

it('counts lines in gzipped file', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'tmdb_test_');
    $gzFile = $tempFile.'.gz';

    $content = implode("\n", [
        '{"id":1}',
        '{"id":2}',
        '{"id":3}',
    ]);

    $gz = gzopen($gzFile, 'w');
    gzwrite($gz, $content);
    gzclose($gz);

    $service = new TMDBService;
    $count = $service->countExportLines($gzFile);

    expect($count)->toBe(3);

    unlink($gzFile);
    @unlink($tempFile);
});
