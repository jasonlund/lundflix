<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\IptorrentsAuthException;
use App\Exceptions\IptorrentsRateLimitExceededException;
use App\Models\Episode;
use App\Models\Movie;
use App\Support\FulfillmentResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TorrentFulfillmentService
{
    public function __construct(private readonly IptorrentsService $ipt) {}

    /**
     * Search IPTorrents for the given media and build the torrent downloads
     * needed to cover them, one search and download per item.
     *
     * @param  Collection<int, Movie|Episode>  $media
     */
    public function fulfill(Collection $media): FulfillmentResult
    {
        /** @var list<array{torrent_id: int, filename: string}> $downloads */
        $downloads = [];
        /** @var Collection<int, Movie|Episode> $covered */
        $covered = collect();

        /** @var Collection<int, Movie> $movies */
        $movies = $media->filter(fn ($item): bool => $item instanceof Movie)->values();
        /** @var Collection<int, Episode> $episodes */
        $episodes = $media->filter(fn ($item): bool => $item instanceof Episode)->values();

        foreach ($movies as $movie) {
            try {
                $result = $this->ipt->searchMovieByName($movie);
            } catch (IptorrentsRateLimitExceededException|IptorrentsAuthException $e) {
                $this->logAborted($e, collect([$movie]));

                throw $e;
            } catch (\Throwable $e) {
                $this->logFailed(collect([$movie]), $e);

                continue;
            }

            if ($result === null) {
                $this->logMissing(collect([$movie]));

                continue;
            }

            $this->logFound($result, collect([$movie]));
            $downloads[] = $this->toDownload($result);
            $covered->push($movie);
        }

        foreach ($episodes as $episode) {
            try {
                $result = $this->ipt->searchEpisodeByName($episode);
            } catch (IptorrentsRateLimitExceededException|IptorrentsAuthException $e) {
                $this->logAborted($e, collect([$episode]));

                throw $e;
            } catch (\Throwable $e) {
                $this->logFailed(collect([$episode]), $e);

                continue;
            }

            if ($result === null) {
                $this->logMissing(collect([$episode]));

                continue;
            }

            $this->logFound($result, collect([$episode]));
            $downloads[] = $this->toDownload($result);
            $covered->push($episode);
        }

        return new FulfillmentResult($downloads, $covered->values());
    }

    /**
     * @param  array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}  $result
     * @return array{torrent_id: int, filename: string}
     */
    private function toDownload(array $result): array
    {
        return [
            'torrent_id' => $result['torrent_id'],
            'filename' => basename((string) parse_url($result['download_url'], PHP_URL_PATH)),
        ];
    }

    /**
     * Record a found torrent and the media it fulfills, so funky matches are
     * traceable after the fact.
     *
     * @param  array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}  $result
     * @param  Collection<int, Movie>|Collection<int, Episode>  $media
     */
    private function logFound(array $result, Collection $media): void
    {
        Log::info('Torrent found', [
            'torrent' => [
                'id' => $result['torrent_id'],
                'name' => $result['name'],
                'size' => $result['size'],
                'seeders' => $result['seeders'],
            ],
            'fulfills' => $media->map(fn (Movie|Episode $item): array => $this->describeMedia($item))->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function describeMedia(Movie|Episode $media): array
    {
        if ($media instanceof Movie) {
            return [
                'type' => 'movie',
                'id' => $media->id,
                'title' => $media->title,
                'year' => $media->year,
            ];
        }

        $media->loadMissing('show');

        return [
            'type' => 'episode',
            'id' => $media->id,
            'show' => $media->show?->name,
            'code' => strtoupper($media->code),
        ];
    }

    /**
     * A search threw an unexpected error; the group is skipped.
     *
     * @param  Collection<int, Movie>|Collection<int, Episode>  $media
     */
    private function logFailed(Collection $media, \Throwable $e): void
    {
        Log::warning('Torrent fulfillment failed', [
            'media' => $media->map(fn (Movie|Episode $item): array => $this->describeMedia($item))->all(),
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * A rate-limit or auth failure halted the whole run.
     *
     * @param  Collection<int, Movie>|Collection<int, Episode>  $media
     */
    private function logAborted(\Throwable $e, Collection $media): void
    {
        Log::warning('Torrent fulfillment aborted', [
            'reason' => class_basename($e),
            'message' => $e->getMessage(),
            'media' => $media->map(fn (Movie|Episode $item): array => $this->describeMedia($item))->all(),
        ]);
    }

    /**
     * The search succeeded but no usable torrent was found.
     *
     * @param  Collection<int, Movie>|Collection<int, Episode>  $media
     */
    private function logMissing(Collection $media): void
    {
        Log::warning('No torrent found', [
            'media' => $media->map(fn (Movie|Episode $item): array => $this->describeMedia($item))->all(),
        ]);
    }
}
