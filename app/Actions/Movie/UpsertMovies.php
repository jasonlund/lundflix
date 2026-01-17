<?php

namespace App\Actions\Movie;

use App\Models\Movie;

class UpsertMovies
{
    /**
     * @param  array<int, array{imdb_id: string, title: string, year: ?int, runtime: ?int, genres: ?string}>  $movies
     */
    public function upsert(array $movies): int
    {
        return Movie::upsert(
            $movies,
            ['imdb_id'],
            ['title', 'year', 'runtime', 'genres']
        );
    }
}
