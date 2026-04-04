<?php

namespace App\Support;

use App\Models\PlexWebhookEvent;
use Illuminate\Support\Collection;

class PlexWebhookFormatter
{
    /**
     * Format a batch of webhook events into a Slack message.
     *
     * @param  Collection<int, PlexWebhookEvent>  $events
     */
    public function format(Collection $events): string
    {
        $serverName = $events->first()->server_name ?? 'Plex';

        $lines = ["*New on {$serverName}:*"];

        $movies = $events->where('media_type', 'movie');
        $episodes = $events->where('media_type', 'episode');

        foreach ($movies->sortBy('title') as $event) {
            $line = $event->title;
            if ($event->year) {
                $line .= " ({$event->year})";
            }
            $lines[] = $line;
        }

        foreach ($this->groupEpisodes($episodes) as $showLine) {
            $lines[] = $showLine;
        }

        return implode("\n", $lines);
    }

    /**
     * Group episodes by show and season, detect runs, return formatted lines.
     *
     * @param  Collection<int, PlexWebhookEvent>  $episodes
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
                    ->map(fn ($n) => (int) $n)
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

            if (! empty($seasonParts)) {
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

        for ($i = 1; $i < count($numbers); $i++) {
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

            if ($run['start'] === $run['end']) {
                $parts[] = $startCode;
            } else {
                $parts[] = sprintf('%s-E%02d', $startCode, $run['end']);
            }
        }

        return implode(', ', $parts);
    }
}
