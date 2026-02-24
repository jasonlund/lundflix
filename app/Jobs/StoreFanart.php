<?php

namespace App\Jobs;

use App\Actions\Fanart\UpsertFanart;
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

    public function handle(FanartTVService $fanart, UpsertFanart $upsertFanart): void
    {
        $response = match (true) {
            $this->model instanceof Movie => ($this->model->tmdb_id ? $fanart->movie((string) $this->model->tmdb_id) : null)
                ?? $fanart->movie($this->model->imdb_id),
            $this->model instanceof Show => $fanart->show($this->model->thetvdb_id),
        };

        if (! $response) {
            return;
        }

        $upsertFanart->upsert($this->model, $response);
    }
}
