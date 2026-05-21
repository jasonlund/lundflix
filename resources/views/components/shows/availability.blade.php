<?php

use App\Models\Episode;
use App\Models\PlexMediaServer;
use App\Models\Show;
use App\Services\ThirdParty\PlexService;
use App\Support\EpisodeCode;
use App\Support\Formatters;
use App\Support\AirDateTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public Show $show;

    public bool $plexLoaded = false;

    public function boot(): void
    {
        $this->show->loadMissing('episodes');
    }

    public function loadPlex(): void
    {
        $this->plexLoaded = true;
        $this->dispatch('plex-show-loaded', availability: $this->episodeAvailability());
    }

    #[On('episodes-loaded')]
    public function refreshAfterEpisodesLoaded(): void
    {
        $this->show->load('episodes');

        if ($this->plexLoaded) {
            $this->dispatch('plex-show-loaded', availability: $this->episodeAvailability());
        }
    }

    #[Computed]
    public function servers(): Collection
    {
        if (! $this->plexLoaded) {
            return collect();
        }

        $user = auth()->user();
        if (! $user?->plex_token || ! $this->show->imdb_id) {
            return collect();
        }

        return Cache::remember("plex:show:{$user->id}:{$this->show->id}", now()->addMinutes(10), function () use (
            $user,
        ) {
            $plex = app(PlexService::class);
            $externalGuid = "imdb://{$this->show->imdb_id}";

            return $plex->searchShowWithEpisodes($user->plex_token, $externalGuid);
        });
    }

    #[Computed]
    public function airedEpisodeCodes(): array
    {
        return $this->show->episodes
            ->filter(
                fn ($episode): bool => ! empty($episode->airdate) &&
                    AirDateTime::hasAired(
                        $episode->airdate,
                        $episode->airtime,
                        $this->show->web_channel,
                        $this->show->network,
                    ),
            )
            ->map(fn ($episode): string => strtoupper(EpisodeCode::generate($episode->season, $episode->number)))
            ->unique()
            ->values()
            ->all();
    }

    #[Computed]
    public function airedEpisodeCount(): int
    {
        return count($this->airedEpisodeCodes);
    }

    /**
     * @return list<array{name: string, clientIdentifier: string, ownerThumb: string|null, isOnline: bool, episodeCount: int, airedCount: int, hasAllAired: bool, tooltip: string, webUrl: string}>
     */
    #[Computed]
    public function serverDisplayData(): array
    {
        if ($this->servers->isEmpty()) {
            return [];
        }

        $airedCount = $this->airedEpisodeCount;
        $airedEpisodeCodes = $this->airedEpisodeCodes;

        $clientIds = $this->servers->pluck('clientIdentifier')->all();
        $plexServers = PlexMediaServer::where('visible', true)
            ->whereIn('client_identifier', $clientIds)
            ->get()
            ->keyBy('client_identifier');

        $regularEpisodes = $this->show->episodes->reject(fn (Episode $ep): bool => $ep->isSpecial());
        $episodesByCode = $regularEpisodes->keyBy(
            fn (Episode $ep): string => strtoupper(EpisodeCode::generate($ep->season, $ep->number)),
        );
        $regularsBySeason = $regularEpisodes->groupBy('season');

        return $this->servers
            ->filter(fn (array $server): bool => $plexServers->has($server['clientIdentifier']))
            ->map(function (array $server) use (
                $airedCount,
                $airedEpisodeCodes,
                $plexServers,
                $episodesByCode,
                $regularsBySeason,
            ): array {
                $plexCodes = collect($server['episodes'])
                    ->map(
                        fn (array $episode): string => strtoupper(
                            EpisodeCode::generate($episode['season'], $episode['episode']),
                        ),
                    )
                    ->unique();

                $episodeCount = $plexCodes->intersect($airedEpisodeCodes)->count();
                $hasAllAired = $airedCount > 0 && $episodeCount === $airedCount;

                $matchedEpisodes = $plexCodes
                    ->map(fn (string $code): ?Episode => $episodesByCode->get($code))
                    ->filter()
                    ->values();

                $seasons = $this->buildSeasonsFromEpisodes($matchedEpisodes, $regularsBySeason);

                $tooltip = $hasAllAired
                    ? "{$server['name']} — All episodes"
                    : "{$server['name']} — {$episodeCount} of {$airedCount} episodes";

                $ratingKey = $server['show']['ratingKey'] ?? '';
                $webUrl = "https://app.plex.tv/desktop/#!/server/{$server['clientIdentifier']}/details?key=%2Flibrary%2Fmetadata%2F{$ratingKey}";

                return [
                    'name' => $server['name'],
                    'clientIdentifier' => $server['clientIdentifier'],
                    'ownerThumb' => $plexServers->get($server['clientIdentifier'])->owner_thumb,
                    'isOnline' => $plexServers->get($server['clientIdentifier'])->is_online,
                    'episodeCount' => $episodeCount,
                    'airedCount' => $airedCount,
                    'hasAllAired' => $hasAllAired,
                    'seasons' => $seasons,
                    'tooltip' => $tooltip,
                    'webUrl' => $webUrl,
                ];
            })
            ->all();
    }

    /**
     * Build season groupings (is_full + runs) matching cart shape, comparing against regulars only.
     *
     * @param  Collection<int, Episode>  $matched
     * @param  Collection<int, Collection<int, Episode>>  $regularsBySeason
     * @return list<array{season: int, is_full: bool, runs: list<Collection<int, Episode>>}>
     */
    private function buildSeasonsFromEpisodes(Collection $matched, Collection $regularsBySeason): array
    {
        if ($matched->isEmpty()) {
            return [];
        }

        $bySeason = $matched->groupBy('season');
        $result = [];

        foreach ($bySeason as $seasonNum => $seasonEpisodes) {
            $seasonRegulars = $regularsBySeason->get($seasonNum, collect());
            $isFull =
                $seasonRegulars->isNotEmpty() &&
                $seasonEpisodes
                    ->pluck('id')
                    ->sort()
                    ->values()
                    ->toArray() ===
                    $seasonRegulars
                        ->pluck('id')
                        ->sort()
                        ->values()
                        ->toArray();

            $runs = $this->findEpisodeRuns($seasonEpisodes, $seasonRegulars);

            $result[] = [
                'season' => (int) $seasonNum,
                'is_full' => $isFull,
                'runs' => $runs,
            ];
        }

        usort($result, fn (array $a, array $b): int => $a['season'] <=> $b['season']);

        return $result;
    }

    /**
     * Find consecutive runs of matched episodes within a season, by episode number order.
     *
     * @param  Collection<int, Episode>  $matched
     * @param  Collection<int, Episode>  $seasonRegulars
     * @return list<Collection<int, Episode>>
     */
    private function findEpisodeRuns(Collection $matched, Collection $seasonRegulars): array
    {
        $sortedAll = $seasonRegulars->sortBy('number')->values();
        $matchedIds = $matched->pluck('id')->all();

        $runs = [];
        $currentRun = collect();

        foreach ($sortedAll as $episode) {
            if (in_array($episode->id, $matchedIds, true)) {
                $currentRun->push($episode);
            } elseif ($currentRun->isNotEmpty()) {
                $runs[] = $currentRun;
                $currentRun = collect();
            }
        }

        if ($currentRun->isNotEmpty()) {
            $runs[] = $currentRun;
        }

        return $runs;
    }

    /**
     * @return array<string, array{label: string, code: string, name: string|null, date: string|null, runtime: string|null}>
     */
    #[Computed]
    public function episodeMilestones(): array
    {
        $episodes = $this->show->episodes;

        if ($episodes->isEmpty()) {
            return [];
        }

        $milestones = [];

        $pilot = $episodes->sortBy(['season', 'number'])->first();
        if ($pilot) {
            $milestones['pilot'] = [
                'label' => 'Pilot',
                'code' => strtoupper($pilot->code),
                'name' => $pilot->name,
                'date' => $pilot->airdate?->format('M j, Y'),
                'runtime' => $pilot->runtime ? Formatters::runtime($pilot->runtime) : null,
            ];
        }

        $lastAired = $episodes
            ->filter(
                fn (Episode $ep): bool => ! empty($ep->airdate) &&
                    AirDateTime::hasAired($ep->airdate, $ep->airtime, $this->show->web_channel, $this->show->network),
            )
            ->sortByDesc(fn (Episode $ep): string => $this->episodeMilestoneSortKey($ep))
            ->first();

        if ($lastAired && $lastAired->id !== $pilot?->id) {
            $milestones['last_aired'] = [
                'label' => 'Last Aired',
                'code' => strtoupper($lastAired->code),
                'name' => $lastAired->name,
                'date' => $lastAired->airdate?->format('M j, Y'),
                'runtime' => $lastAired->runtime ? Formatters::runtime($lastAired->runtime) : null,
            ];
        }

        $nextToAir = $episodes
            ->filter(
                fn (Episode $ep): bool => ! empty($ep->airdate) &&
                    ! AirDateTime::hasAired($ep->airdate, $ep->airtime, $this->show->web_channel, $this->show->network),
            )
            ->sortBy(fn (Episode $ep): string => $this->episodeMilestoneSortKey($ep))
            ->first();

        if ($nextToAir) {
            $milestones['next_to_air'] = [
                'label' => 'Next to Air',
                'code' => strtoupper($nextToAir->code),
                'name' => $nextToAir->name,
                'date' => $nextToAir->airdate?->format('M j, Y'),
                'runtime' => $nextToAir->runtime ? Formatters::runtime($nextToAir->runtime) : null,
            ];
        }

        return $milestones;
    }

    private function episodeMilestoneSortKey(Episode $episode): string
    {
        return sprintf(
            '%010d-%04d-%04d',
            $this->resolvedEpisodeAirDateTime($episode)->timestamp,
            $episode->season,
            $episode->number ?? 0,
        );
    }

    private function resolvedEpisodeAirDateTime(Episode $episode): Carbon
    {
        return AirDateTime::resolve(
            $episode->airdate->format('Y-m-d'),
            $episode->airtime,
            $this->show->web_channel,
            $this->show->network,
        );
    }

    /**
     * Transform server-centric data to episode-centric lookup.
     *
     * @return array<string, list<array{name: string, clientIdentifier: string, ownerThumb: string|null, isOnline: bool, videoResolution: string|null, duration: int|null, webUrl: string}>>
     */
    public function episodeAvailability(): array
    {
        if ($this->servers->isEmpty()) {
            return [];
        }

        $clientIds = $this->servers->pluck('clientIdentifier')->all();
        $plexServers = PlexMediaServer::where('visible', true)
            ->whereIn('client_identifier', $clientIds)
            ->get()
            ->keyBy('client_identifier');

        $availability = [];

        foreach ($this->servers as $server) {
            $plexServer = $plexServers->get($server['clientIdentifier']);
            if (! $plexServer) {
                continue;
            }

            foreach ($server['episodes'] as $ep) {
                $code = strtoupper(EpisodeCode::generate($ep['season'], $ep['episode']));
                $ratingKey = $ep['ratingKey'] ?? '';
                $webUrl = "https://app.plex.tv/desktop/#!/server/{$server['clientIdentifier']}/details?key=%2Flibrary%2Fmetadata%2F{$ratingKey}";
                $resolution = $ep['videoResolution'] ?? null;

                $availability[$code][] = [
                    'name' => $server['name'],
                    'clientIdentifier' => $server['clientIdentifier'],
                    'ownerThumb' => $plexServer->owner_thumb,
                    'isOnline' => $plexServer->is_online,
                    'videoResolution' => Formatters::formatResolution($resolution),
                    'duration' => $ep['duration'] ?? null,
                    'webUrl' => $webUrl,
                ];
            }
        }

        return $availability;
    }
};
?>

<div wire:init="loadPlex">
    <x-section collapsible>
        <x-slot:badge>
            <div class="flex items-center gap-1.5 text-sm">
                @php
                    $yearLabel = Formatters::yearLabel($show);
                    $hasPrevious = false;
                @endphp

                @if ($yearLabel)
                    <span class="text-zinc-300">{{ $yearLabel }}</span>
                    @php
                        $hasPrevious = true;
                    @endphp
                @endif

                @if ($show->status)
                    @if ($hasPrevious)
                        <x-middot />
                    @endif

                    <x-dynamic-component
                        :component="'flux::icon.' . $show->status->icon()"
                        variant="micro"
                        :class="$show->status->iconColorClass()"
                    />
                    <span class="{{ $show->status->iconColorClass() }} text-xs">
                        {{ $show->status->getLabel() }}
                    </span>
                    @php
                        $hasPrevious = true;
                    @endphp
                @endif

                @if ($hasPrevious)
                    <x-middot />
                @endif

                <div class="flex items-center gap-1.5 text-zinc-400">
                    <x-plex-icon class="size-4" />

                    @if (! $plexLoaded)
                        <flux:icon.loading class="size-3" />
                    @elseif (count($this->serverDisplayData) > 0)
                        @foreach ($this->serverDisplayData as $server)
                            @if (! $loop->first)
                                <x-middot />
                            @endif

                            <div class="flex items-center gap-1.5" wire:key="server-{{ $server['clientIdentifier'] }}">
                                <flux:avatar
                                    size="xs"
                                    circle
                                    class="size-4"
                                    :src="$server['ownerThumb']"
                                    :name="$server['name']"
                                    :tooltip="$server['tooltip']"
                                />
                                <span>{{ $server['episodeCount'] }}</span>
                            </div>
                        @endforeach
                    @else
                        <flux:icon.no-symbol variant="micro" class="text-zinc-500" />
                    @endif
                </div>
            </div>
        </x-slot>

        @if (! $plexLoaded)
            <div class="mt-4 flex items-center gap-2 text-sm text-zinc-500">
                <flux:icon.loading class="size-4" />
            </div>
        @elseif (count($this->serverDisplayData) > 0)
            <x-dashboard.list>
                @foreach ($this->serverDisplayData as $server)
                    <x-dashboard.list-row
                        :href="$server['webUrl']"
                        :navigate="false"
                        target="_blank"
                        rel="noopener"
                        class="text-sm"
                        :wire-key="'row-' . $server['clientIdentifier']"
                    >
                        <x-slot:leading>
                            <div class="mt-1 flex shrink-0 items-center gap-2 sm:mt-0">
                                <span
                                    class="{{ $server['isOnline'] ? 'bg-green-500' : 'bg-red-500' }} size-2 shrink-0 rounded-full"
                                ></span>
                                <flux:avatar size="xs" circle :src="$server['ownerThumb']" :name="$server['name']" />
                            </div>
                        </x-slot>

                        @php
                            $seasonLabels = Formatters::seasonRunLabels($server['seasons']);
                        @endphp

                        <div class="flex flex-col gap-0.5">
                            <div>
                                <span
                                    class="block truncate font-serif tracking-wide sm:inline sm:overflow-visible sm:whitespace-normal"
                                >
                                    {{ $server['name'] }}
                                </span>
                                <span class="hidden text-zinc-500 sm:inline">·</span>
                                <span class="text-sm text-zinc-400">
                                    @if ($server['hasAllAired'])
                                        All
                                    @else
                                        {{ $server['episodeCount'] }}
                                    @endif
                                </span>
                            </div>

                            @if (! empty($seasonLabels))
                                <div class="text-sm text-zinc-400">
                                    @foreach ($seasonLabels as $label)
                                        @if (! $loop->first)
                                            <span class="text-zinc-500">·</span>
                                        @endif

                                        <span>{{ $label }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <x-slot:trailing>
                            <flux:icon name="arrow-top-right-on-square" variant="mini" class="shrink-0 text-zinc-400" />
                        </x-slot>
                    </x-dashboard.list-row>
                @endforeach
            </x-dashboard.list>
        @else
            <flux:text class="mt-4 text-zinc-500">Not available on any Plex server.</flux:text>
        @endif

        @if (count($this->episodeMilestones) > 0)
            <flux:separator class="my-4" />
            <flux:heading size="xs" class="text-zinc-400">Episodes</flux:heading>
            <x-dashboard.list>
                @foreach ($this->episodeMilestones as $key => $milestone)
                    <x-dashboard.list-row class="text-sm" :wire-key="'milestone-' . $key">
                        <span class="block truncate sm:inline sm:overflow-visible sm:whitespace-normal">
                            {{ $milestone['label'] }}
                        </span>
                        <span class="hidden text-zinc-500 sm:inline">·</span>
                        <span class="block text-sm text-zinc-400 sm:inline">
                            {{ $milestone['code'] }}
                        </span>
                        @if (! empty($milestone['name']))
                            <span class="hidden text-zinc-500 sm:inline">·</span>
                            <span
                                class="block truncate font-serif tracking-wide text-zinc-400 sm:inline sm:overflow-visible sm:whitespace-normal"
                            >
                                {{ $milestone['name'] }}
                            </span>
                        @endif

                        <x-slot:trailing>
                            <span class="shrink-0 text-right text-sm text-zinc-400">
                                @if ($milestone['date'])
                                    {{ $milestone['date'] }}
                                @endif

                                @if ($milestone['date'] && $milestone['runtime'])
                                    <span class="hidden text-zinc-500 sm:inline">·</span>
                                @endif

                                @if ($milestone['runtime'])
                                    <span class="block text-zinc-500 sm:inline sm:text-xs">
                                        {{ $milestone['runtime'] }}
                                    </span>
                                @endif
                            </span>
                        </x-slot>
                    </x-dashboard.list-row>
                @endforeach
            </x-dashboard.list>
        @endif
    </x-section>
</div>
