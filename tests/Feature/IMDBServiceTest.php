<?php

use App\Services\IMDBService;

it('parses tsv export file correctly', function () {
    // Create a test gzip file with TSV content (IMDb format)
    $tempFile = tempnam(sys_get_temp_dir(), 'imdb_test_');
    $gzFile = $tempFile.'.gz';

    $lines = [
        "tconst\ttitleType\tprimaryTitle\toriginalTitle\tisAdult\tstartYear\tendYear\truntimeMinutes\tgenres",
        "tt0133093\tmovie\tThe Matrix\tThe Matrix\t0\t1999\t\\N\t136\tAction,Sci-Fi",
        "tt0137523\tmovie\tFight Club\tFight Club\t0\t1999\t\\N\t139\tDrama",
        "tt9999999\tmovie\tAdult Film\tAdult Film\t1\t2020\t\\N\t90\tAdult",
        "tt0068646\tmovie\tThe Godfather\tThe Godfather\t0\t1972\t\\N\t175\tCrime,Drama",
        "tt0903747\ttvSeries\tBreaking Bad\tBreaking Bad\t0\t2008\t2013\t45\tCrime,Drama,Thriller",
    ];
    $content = implode("\n", $lines);

    $gz = gzopen($gzFile, 'w');
    gzwrite($gz, $content);
    gzclose($gz);

    $service = new IMDBService;
    $movies = iterator_to_array($service->parseExportFile($gzFile));

    // Should skip adult content and non-movies (tvSeries)
    expect($movies)->toHaveCount(3)
        ->and($movies[0]['imdb_id'])->toBe('tt0133093')
        ->and($movies[0]['title'])->toBe('The Matrix')
        ->and($movies[0]['year'])->toBe(1999)
        ->and($movies[0]['runtime'])->toBe(136)
        ->and($movies[0]['genres'])->toBe('Action,Sci-Fi')
        ->and($movies[1]['imdb_id'])->toBe('tt0137523')
        ->and($movies[2]['imdb_id'])->toBe('tt0068646');

    unlink($gzFile);
    @unlink($tempFile);
});

it('excludes movies with null runtime', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'imdb_test_');
    $gzFile = $tempFile.'.gz';

    $lines = [
        "tconst\ttitleType\tprimaryTitle\toriginalTitle\tisAdult\tstartYear\tendYear\truntimeMinutes\tgenres",
        "tt0000001\tmovie\tNo Runtime\tNo Runtime\t0\t\\N\t\\N\t\\N\t\\N",
        "tt0000002\tmovie\tHas Runtime\tHas Runtime\t0\t2000\t\\N\t90\tDrama",
    ];
    $content = implode("\n", $lines);

    $gz = gzopen($gzFile, 'w');
    gzwrite($gz, $content);
    gzclose($gz);

    $service = new IMDBService;
    $movies = iterator_to_array($service->parseExportFile($gzFile));

    expect($movies)->toHaveCount(1)
        ->and($movies[0]['imdb_id'])->toBe('tt0000002')
        ->and($movies[0]['runtime'])->toBe(90);

    unlink($gzFile);
    @unlink($tempFile);
});

it('counts lines in gzipped file', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'imdb_test_');
    $gzFile = $tempFile.'.gz';

    $lines = [
        "tconst\ttitleType\tprimaryTitle\toriginalTitle\tisAdult\tstartYear\tendYear\truntimeMinutes\tgenres",
        "tt0000001\tmovie\tFilm 1\tFilm 1\t0\t2000\t\\N\t120\tDrama",
        "tt0000002\tmovie\tFilm 2\tFilm 2\t0\t2001\t\\N\t100\tComedy",
        "tt0000003\tmovie\tFilm 3\tFilm 3\t0\t2002\t\\N\t110\tAction",
    ];
    $content = implode("\n", $lines);

    $gz = gzopen($gzFile, 'w');
    gzwrite($gz, $content);
    gzclose($gz);

    $service = new IMDBService;
    $count = $service->countExportLines($gzFile);

    // Count excludes header line
    expect($count)->toBe(3);

    unlink($gzFile);
    @unlink($tempFile);
});
