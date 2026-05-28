<?php

declare(strict_types=1);

namespace App\Services\Torrent;

use App\Enums\EpisodeType;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Show;
use App\Services\IptorrentsService;
use Illuminate\Support\Carbon;

class RequestDownloadPlanner
{
    public function __construct(
        private readonly TorrentResolver $resolver,
        private readonly IptorrentsService $ipt,
    ) {}

    public function plan(Request $request): PlanResult
    {
        if ($request->items->isEmpty()) {
            return PlanResult::empty();
        }

        /** @var array<int, array{torrent_id: int, filename: string}> $downloadsByTorrentId */
        $downloadsByTorrentId = [];
        /** @var list<RequestItem> $notFound */
        $notFound = [];
        /** @var list<RequestItem> $oversize */
        $oversize = [];
        /** @var list<array{show: Show, season: int, pack: array<string, mixed>, requestItems: list<RequestItem>}> $multiSeasonReview */
        $multiSeasonReview = [];
        /** @var list<RequestItem> $packCovered */
        $packCovered = [];

        [$movieItems, $episodeItems] = $this->partition($request);

        foreach ($movieItems as $item) {
            $movie = $item->requestable;

            if (! $movie instanceof Movie) {
                continue;
            }

            $movieRequest = new TorrentRequest(Kind::Movie, $movie, (int) config('torrent.max_bytes.movie'));
            $result = $this->resolver->resolveDetailed($movieRequest);

            if ($result->match !== null) {
                $this->addDownload($downloadsByTorrentId, $result->match);

                continue;
            }

            if ($result->oversizeCandidatesExisted) {
                $oversize[] = $item;
            } else {
                $notFound[] = $item;
            }
        }

        $episodeGroups = $this->groupEpisodes($episodeItems);

        foreach ($episodeGroups as $group) {
            $show = $group['show'];
            $season = $group['season'];
            /** @var list<RequestItem> $groupItems */
            $groupItems = $group['items'];

            $airedRegular = $this->airedRegularEpisodesInSeason($show, $season);
            $isFullSeason = $this->isFullSeason($groupItems, $airedRegular);

            $packCap = $this->packCap(count($airedRegular));

            if ($isFullSeason && $airedRegular !== []) {
                $packRequest = new TorrentRequest(
                    Kind::SeasonPack,
                    ['show' => $show, 'season' => $season],
                    $packCap,
                );

                $packResult = $this->resolver->resolveDetailed($packRequest);

                if ($packResult->match !== null) {
                    $this->addDownload($downloadsByTorrentId, $packResult->match);

                    foreach ($groupItems as $item) {
                        $packCovered[] = $item;
                    }

                    continue;
                }
            }

            /** @var list<RequestItem> $episodeMisses */
            $episodeMisses = [];

            foreach ($groupItems as $item) {
                $episode = $item->requestable;

                if (! $episode instanceof Episode) {
                    continue;
                }

                $episodeRequest = new TorrentRequest(
                    Kind::Episode,
                    $episode,
                    (int) config('torrent.max_bytes.episode'),
                );

                $episodeResult = $this->resolver->resolveDetailed($episodeRequest);

                if ($episodeResult->match !== null) {
                    $this->addDownload($downloadsByTorrentId, $episodeResult->match);

                    continue;
                }

                if ($episodeResult->oversizeCandidatesExisted) {
                    $oversize[] = $item;
                } else {
                    $notFound[] = $item;
                    $episodeMisses[] = $item;
                }
            }

            if ($isFullSeason && $episodeMisses !== []) {
                $multiSeason = $this->ipt->searchMultiSeasonPack($show, $season);

                if ($multiSeason !== null) {
                    $multiSeasonReview[] = [
                        'show' => $show,
                        'season' => $season,
                        'pack' => $multiSeason,
                        'requestItems' => $episodeMisses,
                    ];
                }
            }
        }

        return new PlanResult(
            array_values($downloadsByTorrentId),
            $notFound,
            $oversize,
            $multiSeasonReview,
            $packCovered,
        );
    }

    /**
     * @return array{0: list<RequestItem>, 1: list<RequestItem>}
     */
    private function partition(Request $request): array
    {
        /** @var list<RequestItem> $movies */
        $movies = [];
        /** @var list<RequestItem> $episodes */
        $episodes = [];

        foreach ($request->items as $item) {
            /** @var RequestItem $item */
            $target = $item->requestable;

            if ($target instanceof Movie) {
                $movies[] = $item;
            } elseif ($target instanceof Episode) {
                $episodes[] = $item;
            }
        }

        return [$movies, $episodes];
    }

    /**
     * @param  list<RequestItem>  $episodeItems
     * @return list<array{show: Show, season: int, items: list<RequestItem>}>
     */
    private function groupEpisodes(array $episodeItems): array
    {
        /** @var array<string, array{show: Show, season: int, items: list<RequestItem>}> $groups */
        $groups = [];

        foreach ($episodeItems as $item) {
            $episode = $item->requestable;

            if (! $episode instanceof Episode) {
                continue;
            }

            $show = $episode->show;

            if (! $show instanceof Show) {
                continue;
            }

            $key = $show->id.':'.$episode->season;

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'show' => $show,
                    'season' => (int) $episode->season,
                    'items' => [],
                ];
            }

            $groups[$key]['items'][] = $item;
        }

        return array_values($groups);
    }

    /**
     * @return list<Episode>
     */
    private function airedRegularEpisodesInSeason(Show $show, int $season): array
    {
        $today = Carbon::today();

        return $show->episodes
            ->filter(function (Episode $episode) use ($season, $today): bool {
                if ((int) $episode->season !== $season) {
                    return false;
                }

                if ($episode->type !== EpisodeType::Regular) {
                    return false;
                }

                if ($episode->airdate === null) {
                    return false;
                }

                return $episode->airdate->lessThanOrEqualTo($today); // @phpstan-ignore method.nonObject
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<RequestItem>  $groupItems
     * @param  list<Episode>  $airedRegular
     */
    private function isFullSeason(array $groupItems, array $airedRegular): bool
    {
        if ($airedRegular === []) {
            return false;
        }

        $requestedIds = [];

        foreach ($groupItems as $item) {
            $episode = $item->requestable;

            if ($episode instanceof Episode) {
                $requestedIds[$episode->id] = true;
            }
        }

        foreach ($airedRegular as $episode) {
            if (! isset($requestedIds[$episode->id])) {
                return false;
            }
        }

        return true;
    }

    private function packCap(int $episodeCount): int
    {
        $perEp = (int) config('torrent.max_bytes.pack_per_episode');
        $absolute = (int) config('torrent.max_bytes.pack_absolute');
        $count = max(1, $episodeCount);

        return min($count * $perEp, $absolute);
    }

    /**
     * @param  array<int, array{torrent_id: int, filename: string}>  $downloads
     * @param  array{torrent_id: int, name: string, size: string, seeders: int, leechers: int, snatches: int, uploaded: string, download_url: string}  $match
     */
    private function addDownload(array &$downloads, array $match): void
    {
        if (isset($downloads[$match['torrent_id']])) {
            return;
        }

        $downloads[$match['torrent_id']] = [
            'torrent_id' => $match['torrent_id'],
            'filename' => basename((string) parse_url($match['download_url'], PHP_URL_PATH)),
        ];
    }
}
