<?php

namespace App\Jobs;

use App\Models\Media;
use App\Models\Movie;
use App\Models\Show;
use App\Services\FanartTVService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

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

    public function handle(FanartTVService $fanart): void
    {
        /** @var list<string> $storedPaths */
        $storedPaths = [];
        $modelType = Str::lower(class_basename($this->model));
        $imageTypes = array_filter($this->response, fn ($value) => is_array($value));

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
                    'season' => $image['season'] ?? null,
                    'disc' => $image['disc'] ?? null,
                    'disc_type' => $image['disc_type'] ?? null,
                ];
            }
        }

        try {
            // 2. Find best images and download them
            foreach ($imageTypes as $type => $images) {
                $bestImage = $fanart->bestImage($images);

                if ($bestImage) {
                    $contents = Http::get($bestImage['url'])->throw()->body();
                    $extension = pathinfo((string) parse_url($bestImage['url'], PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                    $path = "fanart/{$modelType}/{$this->model->id}/{$type}/{$bestImage['id']}.{$extension}";

                    Storage::put($path, $contents);
                    $storedPaths[] = $path;

                    // Update the record's path
                    foreach ($records as &$record) {
                        if ($record['fanart_id'] === $bestImage['id'] && $record['type'] === $type) {
                            $record['path'] = $path;
                            break;
                        }
                    }
                }
            }

            // 3. Upsert
            if (! empty($records)) {
                Media::upsert(
                    $records,
                    ['mediable_type', 'mediable_id', 'fanart_id'],
                    ['type', 'url', 'path', 'lang', 'likes', 'season', 'disc', 'disc_type']
                );
            }
        } catch (Throwable $e) {
            // 4. Clean up files on exception
            foreach ($storedPaths as $path) {
                Storage::delete($path);
            }

            throw $e;
        }
    }
}
