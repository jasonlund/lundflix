<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Collection;

class PlexLibraryFormatter
{
    /**
     * Format a collection of library items into a Slack message.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     */
    public function format(?string $serverName, Collection $items): string
    {
        $lines = ['*New on '.($serverName ?? 'Plex').':*'];

        $movies = $items->where('media_type', 'movie');
        $episodes = $items->where('media_type', 'episode');
        $shows = $items->where('media_type', 'show');

        foreach ($movies->sortBy('title') as $item) {
            $line = $item['title'];
            if ($item['year']) {
                $line .= " ({$item['year']})";
            }
            $lines[] = $line;
        }

        foreach ($this->groupEpisodes($episodes) as $showLine) {
            $lines[] = $showLine;
        }

        foreach ($shows->sortBy('title') as $item) {
            $leafCount = $item['leaf_count'] ?? null;
            $line = $item['title'];
            if ($leafCount) {
                $line .= " — {$leafCount} episodes added";
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * Group episodes by show and season, detect runs, return formatted lines.
     *
     * @param  Collection<int, array<string, mixed>>  $episodes
     * @return array<int, string>
     */
    private function groupEpisodes(Collection $episodes): array
    {
        if ($episodes->isEmpty()) {
            return [];
        }

        $lines = [];

        $byShow = $episodes->groupBy('show_title')->sortKeys();

        foreach ($byShow as $showTitle => $showEpisodes) {
            $seasonParts = [];

            $bySeason = $showEpisodes->groupBy('season')->sortKeys();

            foreach ($bySeason as $seasonNum => $seasonEpisodes) {
                $numbers = $seasonEpisodes
                    ->pluck('episode_number')
                    ->filter()
                    ->map(fn ($n): int => (int) $n)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                if (empty($numbers)) {
                    continue;
                }

                $runs = $this->detectRuns($numbers);
                $seasonParts[] = $this->formatRuns((int) $seasonNum, $runs);
            }

            if ($seasonParts !== []) {
                $lines[] = $showTitle.' '.implode(', ', $seasonParts);
            }
        }

        return $lines;
    }

    /**
     * Detect consecutive runs in a sorted array of episode numbers.
     *
     * @param  array<int, int>  $numbers  Sorted, unique episode numbers
     * @return array<int, array{start: int, end: int}>
     */
    private function detectRuns(array $numbers): array
    {
        $runs = [];
        $start = $numbers[0];
        $end = $numbers[0];
        $counter = count($numbers);

        for ($i = 1; $i < $counter; $i++) {
            if ($numbers[$i] === $end + 1) {
                $end = $numbers[$i];
            } else {
                $runs[] = ['start' => $start, 'end' => $end];
                $start = $numbers[$i];
                $end = $numbers[$i];
            }
        }

        $runs[] = ['start' => $start, 'end' => $end];

        return $runs;
    }

    /**
     * Format runs for a season into notation like S01E01-E05 or S01E01, S01E03-E05.
     *
     * @param  array<int, array{start: int, end: int}>  $runs
     */
    private function formatRuns(int $season, array $runs): string
    {
        $seasonPrefix = Formatters::formatSeason($season);
        $parts = [];

        foreach ($runs as $run) {
            $startCode = sprintf('%sE%02d', $seasonPrefix, $run['start']);

            $parts[] = $run['start'] === $run['end'] ? $startCode : sprintf('%s-E%02d', $startCode, $run['end']);
        }

        return implode(', ', $parts);
    }
}
