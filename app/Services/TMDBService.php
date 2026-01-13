<?php

namespace App\Services;

use Generator;
use Illuminate\Support\Facades\Http;

class TMDBService
{
    private const EXPORT_BASE_URL = 'https://files.tmdb.org/p/exports';

    /**
     * Download the daily movie export file.
     *
     * @return string Path to the downloaded gzip file
     */
    public function downloadMovieExport(?string $date = null): string
    {
        $date ??= now()->format('m_d_Y');
        $url = self::EXPORT_BASE_URL."/movie_ids_{$date}.json.gz";

        $tempFile = tempnam(sys_get_temp_dir(), 'tmdb_');
        Http::sink($tempFile)->timeout(300)->get($url)->throw();

        return $tempFile;
    }

    /**
     * Parse the gzipped NDJSON export file.
     * Yields each movie record, skipping adult content and video releases.
     *
     * @return Generator<array>
     */
    public function parseExportFile(string $gzipPath, float $minPopularity = 0): Generator
    {
        $handle = gzopen($gzipPath, 'r');

        while (($line = gzgets($handle)) !== false) {
            $data = json_decode(trim($line), true);

            // Skip adult content, video releases, and below popularity threshold
            if ($data
                && ! ($data['adult'] ?? false)
                && ! ($data['video'] ?? false)
                && ($data['popularity'] ?? 0) >= $minPopularity) {
                yield $data;
            }
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

        while (gzgets($handle) !== false) {
            $count++;
        }

        gzclose($handle);

        return $count;
    }
}
