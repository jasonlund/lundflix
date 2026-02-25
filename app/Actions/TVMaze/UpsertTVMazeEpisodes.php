<?php

namespace App\Actions\TVMaze;

use App\Enums\EpisodeType;
use App\Models\Episode;
use App\Models\Show;
use App\Support\EpisodeCode;

class UpsertTVMazeEpisodes
{
    /**
     * Accept raw TVMaze API episodes and upsert them for a show.
     *
     * @param  array<int, array<string, mixed>>  $apiEpisodes
     */
    public function fromApi(Show $show, array $apiEpisodes): int
    {
        $data = array_map(fn (array $episode) => [
            'tvmaze_id' => $episode['id'],
            'show_id' => $show->id,
            'season' => $episode['season'],
            'number' => $episode['number'],
            'name' => $episode['name'],
            'type' => $episode['type'] ?? 'regular',
            'airdate' => $episode['airdate'] ?? null,
            'airtime' => $episode['airtime'] ?? null,
            'runtime' => $episode['runtime'] ?? null,
            'rating' => $episode['rating'] ?? null,
            'image' => $episode['image'] ?? null,
            'summary' => $episode['summary'] ?? null,
        ], $apiEpisodes);

        return $this->upsert($data);
    }

    /**
     * @param  array<int, array{tvmaze_id: int, show_id: int, season: int, number: ?int, name: string, type?: string, airdate?: ?string, rating?: mixed, image?: mixed, ...}>  $episodes
     */
    public function upsert(array $episodes): int
    {
        $episodes = $this->filterInsignificantSpecials($episodes);
        $episodes = $this->assignSpecialNumbers($episodes);

        // Encode array fields for upsert (model casts don't apply)
        $data = array_map(fn ($ep) => [
            ...$ep,
            'rating' => is_array($ep['rating'] ?? null) ? json_encode($ep['rating']) : $ep['rating'],
            'image' => is_array($ep['image'] ?? null) ? json_encode($ep['image']) : $ep['image'],
        ], $episodes);

        if (empty($data)) {
            return 0;
        }

        return Episode::upsert(
            $data,
            ['tvmaze_id'],
            ['show_id', 'season', 'number', 'name', 'type', 'airdate', 'airtime', 'runtime', 'rating', 'image', 'summary']
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $episodes
     * @return array<int, array<string, mixed>>
     */
    private function filterInsignificantSpecials(array $episodes): array
    {
        return array_values(array_filter(
            $episodes,
            fn (array $ep): bool => ($ep['type'] ?? 'regular') !== EpisodeType::InsignificantSpecial->value
        ));
    }

    /**
     * Assign sequential numbers to significant specials within each season.
     * Numbers are assigned by airdate, with fallback to tvmaze_id/id.
     *
     * @param  array<int, array<string, mixed>>  $episodes
     * @return array<int, array<string, mixed>>
     */
    private function assignSpecialNumbers(array $episodes): array
    {
        $regular = [];
        $specials = [];

        foreach ($episodes as $ep) {
            if (($ep['type'] ?? 'regular') === EpisodeType::SignificantSpecial->value) {
                $specials[] = $ep;
            } else {
                $regular[] = $ep;
            }
        }

        $specialsBySeason = [];
        foreach ($specials as $special) {
            $key = ($special['show_id'] ?? 'all').'_'.$special['season'];
            $specialsBySeason[$key][] = $special;
        }

        if (empty($specialsBySeason)) {
            return $regular;
        }

        foreach ($specialsBySeason as &$group) {
            usort($group, EpisodeCode::compareForSorting(...));

            foreach ($group as $i => &$special) {
                $special['number'] = $i + 1;
            }
        }

        $numberedSpecials = array_merge(...array_values($specialsBySeason));

        return array_merge($regular, $numberedSpecials);
    }
}
