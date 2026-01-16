<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\Movie;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Webmozart\Assert\Assert;

class CartService
{
    private const SESSION_KEY = 'cart';

    /**
     * Get cart items. Movies stored as IDs, episodes as show_id + episode code.
     *
     * @return array{movies: array<int, int>, episodes: array<int, array{show_id: int, code: string}>}
     */
    public function items(): array
    {
        return session(self::SESSION_KEY, [
            'movies' => [],
            'episodes' => [],
        ]);
    }

    /**
     * Add an item to cart. Accepts Model or array (for API episodes).
     *
     * @param  Model|array{show_id?: int, season?: int, number?: int}  $item
     */
    public function add(Model|array $item): void
    {
        $items = $this->items();

        if ($item instanceof Movie) {
            if (! in_array($item->id, $items['movies'])) {
                $items['movies'][] = $item->id;
            }
        } elseif ($item instanceof Episode) {
            $entry = [
                'show_id' => $item->show_id,
                'code' => $this->episodeCode($item->season, $item->number),
            ];
            if (! $this->hasEpisodeEntry($items['episodes'], $entry)) {
                $items['episodes'][] = $entry;
            }
        } elseif (is_array($item) && isset($item['show_id'], $item['season'], $item['number'])) {
            $entry = [
                'show_id' => $item['show_id'],
                'code' => $this->episodeCode($item['season'], $item['number']),
            ];
            if (! $this->hasEpisodeEntry($items['episodes'], $entry)) {
                $items['episodes'][] = $entry;
            }
        }

        session([self::SESSION_KEY => $items]);
    }

    /**
     * Remove an item from cart.
     *
     * @param  Model|array{show_id?: int, season?: int, number?: int}  $item
     */
    public function remove(Model|array $item): void
    {
        $items = $this->items();

        if ($item instanceof Movie) {
            $items['movies'] = array_values(array_filter(
                $items['movies'],
                fn ($id) => $id !== $item->id
            ));
        } elseif ($item instanceof Episode) {
            $entry = [
                'show_id' => $item->show_id,
                'code' => $this->episodeCode($item->season, $item->number),
            ];
            $items['episodes'] = $this->removeEpisodeEntry($items['episodes'], $entry);
        } elseif (is_array($item) && isset($item['show_id'], $item['season'], $item['number'])) {
            $entry = [
                'show_id' => $item['show_id'],
                'code' => $this->episodeCode($item['season'], $item['number']),
            ];
            $items['episodes'] = $this->removeEpisodeEntry($items['episodes'], $entry);
        }

        session([self::SESSION_KEY => $items]);
    }

    /**
     * Check if an item is in the cart.
     *
     * @param  Model|array{show_id?: int, season?: int, number?: int}  $item
     */
    public function has(Model|array $item): bool
    {
        $items = $this->items();

        if ($item instanceof Movie) {
            return in_array($item->id, $items['movies']);
        }

        if ($item instanceof Episode) {
            $entry = [
                'show_id' => $item->show_id,
                'code' => $this->episodeCode($item->season, $item->number),
            ];

            return $this->hasEpisodeEntry($items['episodes'], $entry);
        }

        if (is_array($item) && isset($item['show_id'], $item['season'], $item['number'])) {
            $entry = [
                'show_id' => $item['show_id'],
                'code' => $this->episodeCode($item['season'], $item['number']),
            ];

            return $this->hasEpisodeEntry($items['episodes'], $entry);
        }

        return false;
    }

    public function count(): int
    {
        $items = $this->items();

        return count($items['movies']) + count($items['episodes']);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * Load cart items as Eloquent models.
     *
     * @return Collection<int, Movie|Episode>
     */
    public function loadItems(): Collection
    {
        $items = $this->items();

        $movies = Movie::whereIn('id', $items['movies'])->get();

        // Batch query episodes by show_id + season + number
        $episodes = collect();
        if (! empty($items['episodes'])) {
            $episodes = Episode::with('show')
                ->where(function ($query) use ($items) {
                    foreach ($items['episodes'] as $ep) {
                        $parsed = $this->parseEpisodeCode($ep['code']);
                        $query->orWhere(function ($q) use ($ep, $parsed) {
                            $q->where('show_id', $ep['show_id'])
                                ->where('season', $parsed['season'])
                                ->where('number', $parsed['number']);
                        });
                    }
                })
                ->get();
        }

        return $movies->concat($episodes);
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * Format season and episode number as s01e05.
     */
    private function episodeCode(int $season, int $number): string
    {
        return sprintf('s%02de%02d', $season, $number);
    }

    /**
     * Parse episode code into season and number.
     *
     * @return array{season: int, number: int}
     */
    private function parseEpisodeCode(string $code): array
    {
        $matched = preg_match('/s(\d+)e(\d+)/', $code, $matches);
        Assert::true($matched === 1, sprintf('Invalid episode code format: %s', $code));

        return [
            'season' => (int) $matches[1],
            'number' => (int) $matches[2],
        ];
    }

    /**
     * Check if an episode entry exists in the cart.
     *
     * @param  array<int, array{show_id: int, code: string}>  $episodes
     * @param  array{show_id: int, code: string}  $entry
     */
    private function hasEpisodeEntry(array $episodes, array $entry): bool
    {
        foreach ($episodes as $ep) {
            if ($ep['show_id'] === $entry['show_id'] && $ep['code'] === $entry['code']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove an episode entry from the array.
     *
     * @param  array<int, array{show_id: int, code: string}>  $episodes
     * @param  array{show_id: int, code: string}  $entry
     * @return array<int, array{show_id: int, code: string}>
     */
    private function removeEpisodeEntry(array $episodes, array $entry): array
    {
        return array_values(array_filter($episodes, function ($ep) use ($entry) {
            return ! ($ep['show_id'] === $entry['show_id'] && $ep['code'] === $entry['code']);
        }));
    }
}
