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
    public function format(Collection $items): string
    {
        return $this->formatItems($items, linked: false);
    }

    /**
     * Format a collection of library items into a Slack message with mrkdwn links.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     */
    public function formatLinked(Collection $items): string
    {
        return $this->formatItems($items, linked: true);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     */
    private function formatItems(Collection $items, bool $linked): string
    {
        $lines = [];

        $movies = $items->where('media_type', 'movie');
        $episodes = $items->where('media_type', 'episode');

        foreach ($movies->sortBy('title') as $item) {
            $lines[] = $this->formatMovieLine($item, $linked);
        }

        foreach ($this->groupEpisodes($episodes, $linked) as $showLine) {
            $lines[] = $showLine;
        }

        return implode("\n", $lines);
    }

    /**
     * Group episodes by show and season, detect runs, return formatted lines.
     *
     * @param  Collection<int, array<string, mixed>>  $episodes
     * @return array<int, string>
     */
    private function groupEpisodes(Collection $episodes, bool $linked): array
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
                $seasonParts[] = $this->formatRuns((int) $seasonNum, $runs, $seasonEpisodes, $linked);
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
     * @param  Collection<int, array<string, mixed>>  $seasonEpisodes
     */
    private function formatRuns(int $season, array $runs, Collection $seasonEpisodes, bool $linked): string
    {
        $seasonPrefix = Formatters::formatSeason($season);
        $parts = [];
        $showUrl = $seasonEpisodes
            ->pluck('show_url')
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->first();

        foreach ($runs as $run) {
            $startCode = sprintf('%sE%02d', $seasonPrefix, $run['start']);
            $label = $run['start'] === $run['end']
                ? $startCode
                : sprintf('%s-E%02d', $startCode, $run['end']);

            if (! $linked || ! is_string($showUrl)) {
                $parts[] = $label;

                continue;
            }

            $fragment = $run['start'] === $run['end']
                ? $this->episodeFragment($season, $run['start'])
                : $this->seasonFragment($season);

            $parts[] = $this->formatSlackLink("{$showUrl}#{$fragment}", $label);
        }

        return implode(', ', $parts);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function formatMovieLine(array $item, bool $linked): string
    {
        $label = $item['title'];

        if ($item['year']) {
            $label .= " ({$item['year']})";
        }

        if (! $linked || ! isset($item['url']) || ! is_string($item['url']) || $item['url'] === '') {
            return $label;
        }

        return $this->formatSlackLink($item['url'], $label);
    }

    private function formatSlackLink(string $url, string $label): string
    {
        return "<{$url}|{$label}>";
    }

    private function seasonFragment(int $season): string
    {
        return sprintf('season-s%02d', $season);
    }

    private function episodeFragment(int $season, int $episode): string
    {
        return sprintf('episode-s%02de%02d', $season, $episode);
    }
}
