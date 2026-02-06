<?php

namespace App\Http\Controllers;

use App\Jobs\StoreFanart;
use App\Models\Media;
use App\Models\Movie;
use App\Models\Show;
use App\Services\FanartTVService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Uri;
use Symfony\Component\HttpFoundation\Response;

class ArtController extends Controller
{
    private const MISSING_CACHE_TTL_HOURS = 24 * 7;

    private const ERROR_CACHE_TTL_HOURS = 12;

    private const TYPE_MAP = [
        'logo' => [
            'movie' => [
                \App\Enums\MovieArtwork::HdClearLogo->value,
                \App\Enums\MovieArtwork::HdClearArt->value,
                \App\Enums\MovieArtwork::MovieThumbs->value,
            ],
            'show' => [
                \App\Enums\TvArtwork::HdClearLogo->value,
                \App\Enums\TvArtwork::HdClearArt->value,
                \App\Enums\TvArtwork::TvThumbs->value,
            ],
        ],
        'poster' => [
            'movie' => [\App\Enums\MovieArtwork::Poster->value],
            'show' => [\App\Enums\TvArtwork::Poster->value],
        ],
        'background' => [
            'movie' => [\App\Enums\MovieArtwork::Background->value],
            'show' => [\App\Enums\TvArtwork::Background->value],
        ],
    ];

    public function __invoke(
        string $mediable,
        int $id,
        string $type,
        FanartTVService $fanart
    ): Response {
        $usePreview = request()->boolean('preview');
        $fanartTypes = self::TYPE_MAP[$type][$mediable] ?? [];

        if ($fanartTypes === []) {
            abort(404);
        }

        $model = match ($mediable) {
            'movie' => Movie::findOrFail($id),
            'show' => Show::findOrFail($id),
            default => abort(404),
        };

        if (! $model->canHaveArt()) {
            abort(404);
        }

        $missingCacheKey = $model->artMissingCacheKey();
        $missingTypeCacheKey = $model->artMissingTypeCacheKey($type);
        $media = $this->findActiveMedia($model, $fanartTypes);

        if ($media?->url) {
            return redirect()->away($this->resolveFanartUrl($media->url, $usePreview));
        }

        if (! $model->canFetchArt($type)) {
            abort(404);
        }

        try {
            $response = match (true) {
                $model instanceof Movie => $fanart->movie($model->imdb_id),
                $model instanceof Show => $fanart->show($model->thetvdb_id),
            };
        } catch (\Throwable $e) {
            report($e);
            Cache::put($missingCacheKey, true, now()->addHours(self::ERROR_CACHE_TTL_HOURS));
            abort(404);
        }

        if (! $response) {
            Cache::put($missingCacheKey, true, now()->addHours(self::MISSING_CACHE_TTL_HOURS));
            abort(404);
        }

        $bestImage = $this->findBestImage($response, $fanartTypes, $fanart);

        if (! $bestImage) {
            Cache::put($missingTypeCacheKey, true, now()->addHours(self::MISSING_CACHE_TTL_HOURS));
            abort(404);
        }

        StoreFanart::dispatch($model);
        $imageUrl = $bestImage['url'] ?? null;

        if (! $imageUrl) {
            Cache::put($missingTypeCacheKey, true, now()->addHours(self::MISSING_CACHE_TTL_HOURS));
            abort(404);
        }

        return redirect()->away($this->resolveFanartUrl($imageUrl, $usePreview));
    }

    /**
     * @param  list<string>  $fanartTypes
     */
    private function findActiveMedia(Movie|Show $model, array $fanartTypes): ?Media
    {
        $priority = array_flip($fanartTypes);

        return $model->media()
            ->whereIn('type', $fanartTypes)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (Media $media): int => $priority[$media->type] ?? PHP_INT_MAX)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  list<string>  $fanartTypes
     * @return array<string, mixed>|null
     */
    private function findBestImage(array $response, array $fanartTypes, FanartTVService $fanart): ?array
    {
        foreach ($fanartTypes as $fanartType) {
            $images = $response[$fanartType] ?? null;

            if (! is_array($images) || $images === []) {
                continue;
            }

            return $fanart->bestImage($images) ?? $images[0];
        }

        return null;
    }

    private function resolveFanartUrl(string $url, bool $usePreview): string
    {
        if (! $usePreview) {
            return $url;
        }

        $uri = Uri::of($url);
        $path = $uri->getUri()->getPath();

        if (! str_contains($path, '/fanart/')) {
            return $url;
        }

        return (string) $uri->withPath(str_replace('/fanart/', '/preview/', $path));
    }
}
