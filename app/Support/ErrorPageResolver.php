<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Vite;

class ErrorPageResolver
{
    /** @return array{status: int, src: string, url: string, message: string, description: ?string, caption: ?list<string>}|null */
    public static function resolve(int $status): ?array
    {
        $config = config("error-pages.{$status}");

        if (! $config) {
            return null;
        }

        $video = Arr::random($config['videos']);

        return [
            'status' => $status,
            'src' => Vite::image($video['video']),
            'url' => self::resolveUrl($video),
            'message' => $config['message'],
            'description' => $config['description'] ?? null,
            'caption' => $video['caption'] ?? null,
        ];
    }

    /**
     * Resolve all error pages with all videos for client-side random selection.
     *
     * @return Collection<int, mixed>
     */
    public static function all(): Collection
    {
        /** @var array<int, array{message: string, description: ?string, videos: list<array{video: string, type: string, imdb_id: string, caption?: list<string>}>}> $pages */
        $pages = config('error-pages');

        return collect($pages)
            ->mapWithKeys(fn (array $page, int $code): array => [$code => [
                'message' => $page['message'],
                'description' => $page['description'],
                'videos' => array_map(fn (array $video): array => [
                    'src' => (string) Vite::image($video['video']),
                    'url' => self::resolveUrl($video),
                    'caption' => $video['caption'] ?? null,
                ], $page['videos']),
            ]]);
    }

    /** @param array{type: string, imdb_id: string} $video */
    private static function resolveUrl(array $video): string
    {
        $routeName = $video['type'] === 'movie' ? 'movies.show' : 'shows.show';
        $routeParam = $video['type'] === 'movie' ? 'movie' : 'show';

        return route($routeName, [$routeParam => $video['imdb_id']]);
    }
}
