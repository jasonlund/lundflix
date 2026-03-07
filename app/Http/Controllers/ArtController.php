<?php

namespace App\Http\Controllers;

use App\Enums\ArtworkType;
use App\Models\Movie;
use App\Models\Show;
use App\Support\Sqid;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ArtController extends Controller
{
    private const TYPE_MAP = [
        'logo' => ArtworkType::Logo,
        'poster' => ArtworkType::Poster,
        'background' => ArtworkType::Backdrop,
    ];

    public function __invoke(Request $request, string $mediable, string $id, string $type): Response
    {
        $artworkType = self::TYPE_MAP[$type] ?? null;

        if ($artworkType === null) {
            abort(404);
        }

        $decodedId = Sqid::decode($id);

        if ($decodedId === null) {
            abort(404);
        }

        $model = match ($mediable) {
            'movie' => Movie::findOrFail($decodedId),
            'show' => Show::findOrFail($decodedId),
            default => abort(404),
        };

        if (! $model->canHaveArt()) {
            abort(404);
        }

        $media = $model->media()
            ->where('type', $artworkType->value)
            ->where('is_active', true)
            ->first();

        if (! $media) {
            abort(404);
        }

        $size = $request->query('size');
        $validSize = is_string($size) && in_array($size, ArtworkType::VALID_SIZES) ? $size : null;

        return redirect()->away($media->url($validSize));
    }
}
