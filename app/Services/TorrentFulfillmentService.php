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
    public function __construct(private IptorrentsService $ipt) {}

    /**
     * Search IPTorrents for the given media and build the torrent downloads
     * needed to cover them, preferring season packs over per-episode grabs.
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

        $byShowSeason = $episodes->groupBy(fn (Episode $e): string => $e->show_id.'-'.$e->season);

        foreach ($byShowSeason as $group) {
            try {
                [$groupDownloads, $groupCovered] = $this->fulfillEpisodeGroup($group);
            } catch (IptorrentsRateLimitExceededException|IptorrentsAuthException $e) {
                $this->logAborted($e, $group->values());

                throw $e;
            } catch (\Throwable $e) {
                $this->logFailed($group->values(), $e);

                continue;
            }

            foreach ($groupDownloads as $download) {
                $downloads[] = $download;
            }

            foreach ($groupCovered as $episode) {
                $covered->push($episode);
            }
        }

        return new FulfillmentResult($downloads, $covered->values());
    }

    /**
     * @param  Collection<int, Episode>  $group  Episodes of a single show + season.
     * @return array{0: list<array{torrent_id: int, filename: string}>, 1: Collection<int, Episode>}
     */
    private function fulfillEpisodeGroup(Collection $group): array
    {
        $first = $group->first();
        $season = $first->season;
        $show = $first->loadMissing('show')->show;

        if ($season >= 1) {
            $fullSet = Episode::query()
                ->where('show_id', $show->id)
                ->where('season', $season)
                ->pluck('id');

            $wholeSeasonRequested = $fullSet->isNotEmpty()
                && $fullSet->diff($group->pluck('id'))->isEmpty();

            if ($wholeSeasonRequested) {
                $pack = $this->ipt->searchSeasonPack($show, $season);

                if ($pack !== null) {
                    $this->logFound($pack, $group->values());

                    return [[$this->toDownload($pack)], $group->values()];
                }

                Log::warning('No season pack found, falling back to per-episode', [
                    'show' => $show->name,
                    'season' => $season,
                ]);
            }
        }

        /** @var list<array{torrent_id: int, filename: string}> $downloads */
        $downloads = [];
        /** @var Collection<int, Episode> $covered */
        $covered = collect();

        $airdateGroups = $group->groupBy(
            fn (Episode $e): string => $e->airdate?->format('Y-m-d').'|'.$e->airtime, // @phpstan-ignore method.nonObject (casted to Carbon)
        );

        foreach ($airdateGroups as $subgroup) {
            $probe = $subgroup->sortBy('number')->first();
            $result = $this->ipt->searchEpisodeByName($probe);

            if ($result === null) {
                $this->logMissing($subgroup->values());

                continue;
            }

            $this->logFound($result, $subgroup->values());
            $downloads[] = $this->toDownload($result);

            foreach ($subgroup as $episode) {
                $covered->push($episode);
            }
        }

        return [$downloads, $covered];
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
