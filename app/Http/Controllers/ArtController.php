<?php

namespace App\Http\Controllers;

use App\Jobs\StoreFanart;
use App\Models\Movie;
use App\Models\Show;
use App\Services\FanartTVService;
use Illuminate\Http\RedirectResponse;

class ArtController extends Controller
{
    public function __invoke(
        string $mediable,
        int $id,
        string $type,
        FanartTVService $fanart
    ): RedirectResponse {
        $model = match ($mediable) {
            'movie' => Movie::findOrFail($id),
            'show' => Show::findOrFail($id),
            default => abort(404),
        };

        $media = $model->media()->where('type', $type)->first();

        if ($media) {
            return redirect($media->url);
        }

        $response = match (true) {
            $model instanceof Movie => $fanart->movie($model->imdb_id),
            $model instanceof Show => $fanart->show($model->externals['thetvdb']), // @phpstan-ignore offsetAccess.notFound (externals is cast to array)
        };

        if (! $response || ! isset($response[$type])) {
            abort(404);
        }

        StoreFanart::dispatch($model, $response);

        return redirect($response[$type][0]['url']);
    }
}
