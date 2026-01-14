<?php

namespace App\Services;

use Generator;
use Illuminate\Support\Facades\Http;

class IMDBService
{
    private const EXPORT_URL = 'https://datasets.imdbws.com/title.basics.tsv.gz';

    private const RATINGS_URL = 'https://datasets.imdbws.com/title.ratings.tsv.gz';

    /**
     * Download the daily title basics export file.
     *
     * @return string Path to the downloaded gzip file
     */
    public function downloadExport(): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'imdb_');
        Http::sink($tempFile)->timeout(600)->get(self::EXPORT_URL)->throw();

        return $tempFile;
    }

    /**
     * Parse the gzipped TSV export file.
     * Yields movie records only, skipping adult content and short films.
     *
     * @return Generator<array>
     */
    public function parseExportFile(string $gzipPath): Generator
    {
        $handle = gzopen($gzipPath, 'r');

        // Skip header line
        gzgets($handle);

        while (($line = gzgets($handle)) !== false) {
            $fields = explode("\t", trim($line));

            // Fields: tconst, titleType, primaryTitle, originalTitle, isAdult, startYear, endYear, runtimeMinutes, genres
            if (count($fields) < 9) {
                continue;
            }

            [$tconst, $titleType, $primaryTitle, $originalTitle, $isAdult, $startYear, $endYear, $runtimeMinutes, $genres] = $fields;

            // Only movies, no adult content
            if ($titleType !== 'movie' || $isAdult === '1') {
                continue;
            }

            // Exclude entries without runtime
            $runtime = $runtimeMinutes !== '\\N' ? (int) $runtimeMinutes : null;
            if ($runtime === null) {
                continue;
            }

            yield [
                'imdb_id' => $tconst,
                'title' => $primaryTitle,
                'year' => $startYear !== '\\N' ? (int) $startYear : null,
                'runtime' => $runtime,
                'genres' => $genres !== '\\N' ? $genres : null,
            ];
        }

        gzclose($handle);
    }

    /**
     * Count lines in a gzipped file for progress tracking.
     */
    public function countExportLines(string $gzipPath): int
    {
        $count = 0;
        $handle = gzopen($gzipPath, 'r');

        // Skip header
        gzgets($handle);

        while (gzgets($handle) !== false) {
            $count++;
        }

        gzclose($handle);

        return $count;
    }

    /**
     * Download the daily ratings export file.
     *
     * @return string Path to the downloaded gzip file
     */
    public function downloadRatings(): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'imdb_ratings_');
        Http::sink($tempFile)->timeout(600)->get(self::RATINGS_URL)->throw();

        return $tempFile;
    }

    /**
     * Parse the gzipped ratings TSV file into a lookup array.
     *
     * @return array<string, int> Map of imdb_id => num_votes
     */
    public function parseRatingsFile(string $gzipPath): array
    {
        $ratings = [];
        $handle = gzopen($gzipPath, 'r');

        // Skip header line (tconst, averageRating, numVotes)
        gzgets($handle);

        while (($line = gzgets($handle)) !== false) {
            $fields = explode("\t", trim($line));

            if (count($fields) < 3) {
                continue;
            }

            [$tconst, $averageRating, $numVotes] = $fields;

            $ratings[$tconst] = (int) $numVotes;
        }

        gzclose($handle);

        return $ratings;
    }
}
