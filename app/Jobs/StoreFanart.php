<?php

namespace App\Jobs;

use App\Models\Media;
use App\Models\Movie;
use App\Models\Show;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StoreFanart implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $response
     */
    public function __construct(
        public Movie|Show $model,
        public array $response
    ) {}

    public function handle(): void
    {
        $records = [];

        foreach ($this->response as $type => $images) {
            foreach ($images as $image) {
                $records[] = [
                    'mediable_type' => $this->model->getMorphClass(),
                    'mediable_id' => $this->model->id,
                    'fanart_id' => $image['id'],
                    'type' => $type,
                    'url' => $image['url'],
                    'lang' => $image['lang'] ?? null,
                    'likes' => (int) ($image['likes'] ?? 0),
                    'season' => $image['season'] ?? null,
                    'disc' => $image['disc'] ?? null,
                    'disc_type' => $image['disc_type'] ?? null,
                ];
            }
        }

        if (! empty($records)) {
            Media::upsert(
                $records,
                ['mediable_type', 'mediable_id', 'fanart_id'],
                ['type', 'url', 'lang', 'likes', 'season', 'disc', 'disc_type']
            );
        }
    }
}
