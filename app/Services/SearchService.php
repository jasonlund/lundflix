<?php

namespace App\Services;

use App\Models\Movie;
use App\Models\Show;
use Illuminate\Support\Collection;

class SearchService
{
    /**
     * Search shows and/or movies by query.
     *
     * @param  string  $query  Search term or IMDB ID (tt*)
     * @param  string  $type  Filter: 'shows', 'movies', or 'all'
     */
    public function search(string $query, string $type = 'all'): Collection
    {
        $query = trim($query);

        if ($query === '') {
            return collect();
        }

        if (preg_match('/^tt\d+$/i', $query)) {
            return $this->findByImdbId($query, $type);
        }

        return $this->fullTextSearch($query, $type);
    }

    private function findByImdbId(string $imdbId, string $type): Collection
    {
        $results = collect();

        if ($type === 'all' || $type === 'shows') {
            $show = Show::where('imdb_id', $imdbId)->first();
            if ($show) {
                $results->push($show);
            }
        }

        if ($type === 'all' || $type === 'movies') {
            $movie = Movie::where('imdb_id', $imdbId)->first();
            if ($movie) {
                $results->push($movie);
            }
        }

        return $results;
    }

    private function fullTextSearch(string $query, string $type): Collection
    {
        return match ($type) {
            'shows' => Show::search($query)->get(),
            'movies' => Movie::search($query)->get(),
            default => $this->searchBoth($query),
        };
    }

    private function searchBoth(string $query): Collection
    {
        $shows = Show::search($query)->get();
        $movies = Movie::search($query)->get();

        return $shows->merge($movies);
    }
}
