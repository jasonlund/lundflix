<?php

namespace App\Http\Controllers;

use App\Jobs\StoreFanart;
use App\Models\Movie;
use App\Models\Show;
use App\Services\FanartTVService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ArtController extends Controller
{
    public function __invoke(
        string $mediable,
        int $id,
        string $type,
        FanartTVService $fanart
    ): Response {
        $model = match ($mediable) {
            'movie' => Movie::findOrFail($id),
            'show' => Show::findOrFail($id),
            default => abort(404),
        };

        if ($model instanceof Show && ! $model->thetvdb_id) {
            abort(404);
        }

        $media = $model->media()->where('type', $type)->first();

        if ($media?->path && Storage::exists($media->path)) {
            return $this->imageResponse(Storage::get($media->path), $media->path);
        }

        $response = match (true) {
            $model instanceof Movie => $fanart->movie($model->imdb_id),
            $model instanceof Show => $fanart->show($model->thetvdb_id),
        };

        if (! $response || ! isset($response[$type]) || empty($response[$type])) {
            abort(404);
        }

        StoreFanart::dispatch($model, $response);

        $bestImage = $fanart->bestImage($response[$type]) ?? $response[$type][0];
        $imageUrl = $bestImage['url'];

        try {
            $contents = Http::get($imageUrl)->throw()->body();
        } catch (Throwable $e) {
            Log::error('Failed to fetch fanart image', [
                'url' => $imageUrl,
                'model' => $model::class,
                'model_id' => $model->id,
                'exception' => $e->getMessage(),
            ]);
            abort(404);
        }

        return $this->imageResponse($contents, $imageUrl);
    }

    private function imageResponse(string $contents, string $path): Response
    {
        return response($contents)
            ->header('Content-Type', $this->getMimeType($path))
            ->header('Cache-Control', 'private, max-age=604800');
    }

    private function getMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }
}
