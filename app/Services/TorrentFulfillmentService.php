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
                throw $e;
            } catch (\Throwable $e) {
                $this->reportGroupFailure(['movie_id' => $movie->id], $e);

                continue;
            }

            if ($result !== null) {
                $downloads[] = $this->toDownload($result);
                $covered->push($movie);
            }
        }

        $byShowSeason = $episodes->groupBy(fn (Episode $e): string => $e->show_id.'-'.$e->season);

        foreach ($byShowSeason as $group) {
            try {
                [$groupDownloads, $groupCovered] = $this->fulfillEpisodeGroup($group);
            } catch (IptorrentsRateLimitExceededException|IptorrentsAuthException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $this->reportGroupFailure([
                    'show_id' => $group->first()->show_id,
                    'season' => $group->first()->season,
                ], $e);

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
                    return [[$this->toDownload($pack)], $group->values()];
                }
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
                continue;
            }

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
     * @param  array<string, mixed>  $context
     */
    private function reportGroupFailure(array $context, \Throwable $e): void
    {
        Log::warning('Torrent fulfillment failed', [
            ...$context,
            'error' => $e->getMessage(),
        ]);
    }
}
