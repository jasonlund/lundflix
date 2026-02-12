<?php

namespace App\Jobs;

use App\Models\Media;
use App\Models\Movie;
use App\Models\Show;
use App\Services\FanartTVService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StoreFanart implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(
        public Movie|Show $model,
    ) {}

    public function uniqueId(): string
    {
        return $this->model->getMorphClass().':'.$this->model->id;
    }

    public function handle(FanartTVService $fanart): void
    {
        $response = match (true) {
            $this->model instanceof Movie => ($this->model->tmdb_id ? $fanart->movie((string) $this->model->tmdb_id) : null)
                ?? $fanart->movie($this->model->imdb_id),
            $this->model instanceof Show => $fanart->show($this->model->thetvdb_id),
        };

        if (! $response) {
            return;
        }

        $imageTypes = array_filter($response, fn ($value) => is_array($value));

        // 1. Build records array
        $records = [];
        foreach ($imageTypes as $type => $images) {
            foreach ($images as $image) {
                $records[] = [
                    'mediable_type' => $this->model->getMorphClass(),
                    'mediable_id' => $this->model->id,
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
                $bestImage = $fanart->bestImage($seasonImages->all());

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
