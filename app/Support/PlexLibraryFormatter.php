<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Collection;

class PlexLibraryFormatter
{
    public function __construct(
        private ?string $clientIdentifier = null,
    ) {}

    /**
     * Format a collection of library items into a Slack message.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     */
    public function format(Collection $items): string
    {
        $lines = [];

        $movies = $items->where('media_type', 'movie');
        $episodes = $items->where('media_type', 'episode');

        foreach ($movies->sortBy('title') as $item) {
            $label = $item['title'];

            if ($item['year']) {
                $label .= " ({$item['year']})";
            }

            $url = $this->plexUrl($item['rating_key'] ?? '');

            $lines[] = $url ? "{$label} <{$url}|↗️>" : $label;
        }

        foreach ($this->groupEpisodes($episodes) as $showLine) {
            $lines[] = $showLine;
        }

        return implode("\n", $lines);
    }

    /**
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
                $label = $showTitle.' '.implode(', ', $seasonParts);
                $url = $this->resolveShowLinkUrl($showEpisodes, $bySeason);

                $lines[] = $url ? "{$label} <{$url}|↗️>" : $label;
            }
        }

        return $lines;
    }

    /**
     * Determine the Plex link target based on episode scope.
     *
     * @param  Collection<int, array<string, mixed>>  $showEpisodes
     * @param  Collection<int|string, Collection<int, array<string, mixed>>>  $bySeason
     */
    private function resolveShowLinkUrl(Collection $showEpisodes, Collection $bySeason): ?string
    {
        $seasonCount = $bySeason->count();

        if ($seasonCount > 1) {
            return $this->plexUrl($showEpisodes->first()['grandparent_rating_key'] ?? '');
        }

        $episodeCount = $showEpisodes->count();

        if ($episodeCount === 1) {
            return $this->plexUrl($showEpisodes->first()['rating_key'] ?? '');
        }

        return $this->plexUrl($showEpisodes->first()['parent_rating_key'] ?? '');
    }

    private function plexUrl(string $ratingKey): ?string
    {
        if ($this->clientIdentifier === null || $ratingKey === '') {
            return null;
        }

        return "https://app.plex.tv/desktop/#!/server/{$this->clientIdentifier}/details?key=%2Flibrary%2Fmetadata%2F{$ratingKey}";
    }

    /**
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
     * @param  array<int, array{start: int, end: int}>  $runs
     */
    private function formatRuns(int $season, array $runs): string
    {
        $seasonPrefix = Formatters::formatSeason($season);
        $parts = [];

        foreach ($runs as $run) {
            $startCode = sprintf('%sE%02d', $seasonPrefix, $run['start']);
            $parts[] = $run['start'] === $run['end']
                ? $startCode
                : sprintf('%s-E%02d', $startCode, $run['end']);
        }

        return implode(', ', $parts);
    }
}
