<?php

declare(strict_types=1);

namespace App\Actions\IMDB;

use App\Models\Movie;
use App\Support\DatabaseRetry;

class UpsertIMDBMovies
{
    /**
     * @param  array<int, array{imdb_id: string, title: string, year: ?int, runtime: ?int, genres: ?array}>  $movies
     */
    public function upsert(array $movies): int
    {
        // Encode genres arrays to JSON for database storage
        $movies = array_map(function (array $movie): array {
            if (isset($movie['genres'])) {
                $movie['genres'] = json_encode($movie['genres']);
            }

            return $movie;
        }, $movies);

        $imdbIds = array_column($movies, 'imdb_id');

        $count = DatabaseRetry::run(fn (): int => Movie::upsert(
            $movies,
            ['imdb_id'],
            ['title', 'year', 'runtime', 'genres']
        ));

        Movie::whereIn('imdb_id', $imdbIds)->get()->searchable(); // @phpstan-ignore method.notFound (Scout collection macro)

        return $count;
    }
}
