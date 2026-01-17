<?php

namespace App\Actions\Movie;

use Illuminate\Support\Facades\DB;

class SyncMovieRatings
{
    /**
     * @param  array<string, int>  $ratings  IMDb ID => num_votes
     */
    public function sync(array $ratings): int
    {
        if (empty($ratings)) {
            return 0;
        }

        $ids = array_keys($ratings);
        $cases = [];
        $bindings = [];

        foreach ($ratings as $imdbId => $numVotes) {
            $cases[] = 'WHEN imdb_id = ? THEN ?';
            $bindings[] = $imdbId;
            $bindings[] = $numVotes;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $caseStatement = implode(' ', $cases);

        $sql = "UPDATE movies SET num_votes = CASE {$caseStatement} END WHERE imdb_id IN ({$placeholders})";
        $bindings = array_merge($bindings, $ids);

        return DB::update($sql, $bindings);
    }
}
