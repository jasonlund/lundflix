<?php

namespace App\Actions\TMDB;

use App\Enums\ArtworkType;
use App\Models\Media;
use App\Models\Movie;
use App\Models\Show;
use App\Support\DatabaseRetry;

class UpsertTMDBImages
{
    /**
     * Store TMDB images for a movie or show.
     *
     * @param  array<string, mixed>  $imagesResponse  TMDB images response with posters[], backdrops[], logos[]
     */
    public function upsert(Movie|Show $model, array $imagesResponse): void
    {
        $typeMapping = [
            'posters' => ArtworkType::Poster,
            'backdrops' => ArtworkType::Backdrop,
            'logos' => ArtworkType::Logo,
        ];

        $records = [];
        $managedTypes = array_map(
            fn (ArtworkType $artworkType): string => $artworkType->value,
            array_values($typeMapping),
        );

        DatabaseRetry::run(fn (): int => $model->media()
            ->whereIn('type', $managedTypes)
            ->update(['is_active' => false]));

        foreach ($typeMapping as $key => $artworkType) {
            $images = $imagesResponse[$key] ?? [];

            if (! is_array($images) || $images === []) {
                continue;
            }

            $best = $this->findBestImage($images, $artworkType);

            foreach ($images as $image) {
                $filePath = $image['file_path'] ?? null;

                if (! $filePath) {
                    continue;
                }

                $records[] = [
                    'mediable_type' => $model->getMorphClass(),
                    'mediable_id' => $model->id,
                    'file_path' => $filePath,
                    'type' => $artworkType->value,
                    'path' => null,
                    'lang' => $image['iso_639_1'] ?? null,
                    'vote_average' => (float) ($image['vote_average'] ?? 0),
                    'vote_count' => (int) ($image['vote_count'] ?? 0),
                    'width' => $image['width'] ?? null,
                    'height' => $image['height'] ?? null,
                    'season' => null,
                    'is_active' => $filePath === $best,
                ];
            }
        }

        if ($records !== []) {
            DatabaseRetry::run(fn (): int => Media::upsert(
                $records,
                ['mediable_type', 'mediable_id', 'file_path'],
                ['type', 'lang', 'vote_average', 'vote_count', 'width', 'height', 'is_active']
            ));
        }
    }

    /**
     * Find the best image file_path.
     *
     * For backdrops, prefer null-language (textless) images — images tagged with
     * a language typically have titles/text baked in. For posters and logos, prefer
     * English then null. Within each language tier, sort by vote_average desc, vote_count desc.
     *
     * @param  array<int, array<string, mixed>>  $images
     */
    private function findBestImage(array $images, ArtworkType $type): ?string
    {
        $preferTextless = $type === ArtworkType::Backdrop;

        return collect($images)
            ->filter(fn (array $img): bool => isset($img['file_path']))
            ->filter(fn (array $img): bool => in_array($img['iso_639_1'] ?? null, ['en', null]))
            ->sort(function (array $a, array $b) use ($preferTextless): int {
                if ($preferTextless) {
                    $aNull = ($a['iso_639_1'] ?? null) === null;
                    $bNull = ($b['iso_639_1'] ?? null) === null;

                    if ($aNull !== $bNull) {
                        return $aNull ? -1 : 1;
                    }
                }

                $avgDiff = (float) ($b['vote_average'] ?? 0) <=> (float) ($a['vote_average'] ?? 0);

                if ($avgDiff !== 0) {
                    return $avgDiff;
                }

                return (int) ($b['vote_count'] ?? 0) <=> (int) ($a['vote_count'] ?? 0);
            })
            ->first()['file_path'] ?? null;
    }
}
