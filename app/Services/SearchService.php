<?php

namespace App\Services;

use App\Models\Movie;
use App\Models\Show;
use Illuminate\Support\Collection;
use Meilisearch\Client;
use Meilisearch\Contracts\MultiSearchFederation;
use Meilisearch\Contracts\SearchQuery;

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
        if (config('scout.driver') !== 'meilisearch') {
            return Show::search($query)->get()
                ->merge(Movie::search($query)->get());
        }

        $client = app(Client::class);

        $results = $client->multiSearch(
            [
                (new SearchQuery)->setIndexUid((new Show)->searchableAs())->setQuery($query),
                (new SearchQuery)->setIndexUid((new Movie)->searchableAs())->setQuery($query),
            ],
            new MultiSearchFederation
        );

        return collect($results['hits'])->map(function ($hit) {
            $indexUid = $hit['_federation']['indexUid'];

            if (str_contains($indexUid, 'shows')) {
                return Show::find($hit['id']);
            }

            return Movie::find($hit['id']);
        })->filter();
    }
}
