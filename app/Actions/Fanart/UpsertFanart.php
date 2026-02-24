<?php

namespace App\Actions\Fanart;

use App\Models\Media;
use App\Models\Movie;
use App\Models\Show;
use App\Services\FanartTVService;

class UpsertFanart
{
    public function __construct(public FanartTVService $fanart) {}

    /**
     * Build media records from a FanArt API response and upsert them.
     *
     * @param  array<string, mixed>  $response
     */
    public function upsert(Movie|Show $model, array $response): void
    {
        $imageTypes = array_filter($response, fn ($value) => is_array($value));

        // 1. Build records array
        $records = [];
        foreach ($imageTypes as $type => $images) {
            foreach ($images as $image) {
                $records[] = [
                    'mediable_type' => $model->getMorphClass(),
                    'mediable_id' => $model->id,
                    'fanart_id' => $image['id'],
                    'type' => $type,
                    'url' => $image['url'],
                    'path' => null,
                    'lang' => $image['lang'] ?? null,
                    'likes' => (int) ($image['likes'] ?? 0),
                    'season' => match ($image['season'] ?? null) {
                        null => null,
                        'all' => 0,
                        default => (int) $image['season'],
                    },
                    'disc' => $image['disc'] ?? null,
                    'disc_type' => $image['disc_type'] ?? null,
                    'is_active' => false,
                ];
            }
        }

        // 2. Find best images per type+season and mark them active
        $bestIdsByType = [];
        foreach ($imageTypes as $type => $images) {
            $imagesBySeason = collect($images)->groupBy(fn ($img) => match ($img['season'] ?? null) {
                null => 'null',
                'all' => '0',
                default => (string) $img['season'],
            });

            foreach ($imagesBySeason as $seasonKey => $seasonImages) {
                $bestImage = $this->fanart->bestImage($seasonImages->all());

                if ($bestImage) {
                    $bestIdsByType[$type][$seasonKey] = $bestImage['id'];
                }
            }
        }

        foreach ($records as &$record) {
            $seasonKey = match ($record['season']) {
                null => 'null',
                0 => '0',
                default => (string) $record['season'],
            };

            if (($bestIdsByType[$record['type']][$seasonKey] ?? null) === $record['fanart_id']) {
                $record['is_active'] = true;
            }
        }

        // 3. Upsert
        if (! empty($records)) {
            Media::upsert(
                $records,
                ['mediable_type', 'mediable_id', 'fanart_id'],
                ['type', 'url', 'path', 'lang', 'likes', 'season', 'disc', 'disc_type', 'is_active']
            );
        }
    }
}
