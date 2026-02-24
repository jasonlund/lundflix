<?php

namespace App\Actions\IMDB;

use App\Models\Movie;

class UpsertIMDBMovies
{
    /**
     * @param  array<int, array{imdb_id: string, title: string, year: ?int, runtime: ?int, genres: ?array}>  $movies
     */
    public function upsert(array $movies): int
    {
        // Encode genres arrays to JSON for database storage
        $movies = array_map(function ($movie) {
            if (isset($movie['genres'])) {
                $movie['genres'] = json_encode($movie['genres']);
            }

            return $movie;
        }, $movies);

        return Movie::upsert(
            $movies,
            ['imdb_id'],
            ['title', 'year', 'runtime', 'genres']
        );
    }
}
