<?php

namespace App\Services;

use App\Enums\Language;
use App\Models\Movie;
use App\Models\Show;
use Illuminate\Support\Collection;

class SearchService
{
    /**
     * Determine if a query string is an IMDb ID (e.g. tt1234567).
     */
    public static function isImdbId(string $query): bool
    {
        return (bool) preg_match('/^tt\d+$/i', $query);
    }

    /**
     * Search shows and/or movies by query.
     *
     * Falls back to the database Scout driver if the primary driver fails
     * (e.g. Algolia quota exceeded or API unavailable).
     *
     * @param  string  $query  Search term or IMDB ID (tt*)
     * @param  string  $type  Filter: 'shows', 'movies', or 'all'
     * @param  string|null  $language  Language filter: 'en' for English, 'foreign' for non-English, null for all
     */
    public function search(string $query, string $type = 'all', ?string $language = null): Collection
    {
        $query = trim($query);

        if ($query === '') {
            return collect();
        }

        try {
            return $this->executeSearch($query, $type, $language);
        } catch (\Exception $e) {
            if (config('scout.driver') === 'database') {
                throw $e;
            }

            report($e);
            config(['scout.driver' => 'database']);

            return $this->executeSearch($query, $type, $language);
        }
    }

    private function executeSearch(string $query, string $type, ?string $language): Collection
    {
        if (self::isImdbId($query)) {
            return $this->findByImdbId($query, $type);
        }

        return $this->fullTextSearch($query, $type, $language);
    }

    private function findByImdbId(string $imdbId, string $type): Collection
    {
        $results = collect();

        if ($type === 'all' || $type === 'shows') {
            $results = $results->merge(
                Show::search('')->where('imdb_id', $imdbId)->get()
            );
        }

        if ($type === 'all' || $type === 'movies') {
            $results = $results->merge(
                Movie::search('')->where('imdb_id', $imdbId)->get()
            );
        }

        return $results;
    }

    private function fullTextSearch(string $query, string $type, ?string $language): Collection
    {
        return match ($type) {
            'shows' => $this->filterByLanguage(Show::search($query)->get(), $language)->sortByDesc('num_votes')->values(),
            'movies' => $this->filterByLanguage(Movie::search($query)->get(), $language)->sortByDesc('num_votes')->values(),
            default => $this->searchBoth($query, $language),
        };
    }

    private function searchBoth(string $query, ?string $language): Collection
    {
        $results = Show::search($query)->get()->toBase()
            ->merge(Movie::search($query)->get()); // @phpstan-ignore argument.type

        return $this->filterByLanguage($results, $language)->sortByDesc('num_votes')->values();
    }

    /**
     * Filter a collection of results by language.
     */
    private function filterByLanguage(Collection $results, ?string $language): Collection
    {
        if ($language === null) {
            return $results;
        }

        return $results->filter(function (Show|Movie $item) use ($language): bool {
            $itemLanguage = $item instanceof Movie
                ? $item->original_language
                : $item->language;

            $iso = $itemLanguage?->value; // @phpstan-ignore property.nonObject (casted to Language enum)

            if ($language === 'foreign') {
                return $iso !== null && $iso !== Language::English->value;
            }

            return $iso === $language;
        })->values();
    }
}
