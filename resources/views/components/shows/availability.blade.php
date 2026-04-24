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

    public function placeholder(): string
    {
        return <<<'HTML'
        <div>
            <flux:card class="cursor-wait overflow-hidden p-3">
                <div class="flex w-full items-center">
                    <div class="flex items-center gap-2">
                        <flux:icon.loading class="size-4 text-zinc-400" />
                    </div>
                </div>
            </flux:card>
        </div>
        HTML;
    }

    public function boot(): void
    {
        $this->show->loadMissing('episodes');
    }

    public function mount(): void
    {
        $this->dispatch('plex-show-loaded', availability: $this->episodeAvailability());
    }

    #[On('episodes-loaded')]
    public function refreshAfterEpisodesLoaded(): void
    {
        $this->show->load('episodes');
        $this->dispatch('plex-show-loaded', availability: $this->episodeAvailability());
    }

    #[Computed]
    public function servers(): Collection
    {
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

        return $this->servers
            ->filter(fn (array $server): bool => $plexServers->has($server['clientIdentifier']))
            ->map(function (array $server) use ($airedCount, $airedEpisodeCodes, $plexServers): array {
                $episodeCount = collect($server['episodes'])
                    ->map(
                        fn (array $episode): string => strtoupper(
                            EpisodeCode::generate($episode['season'], $episode['episode']),
                        ),
                    )
                    ->unique()
                    ->intersect($airedEpisodeCodes)
                    ->count();

                $hasAllAired = $airedCount > 0 && $episodeCount === $airedCount;

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
                    'tooltip' => $tooltip,
                    'webUrl' => $webUrl,
                ];
            })
            ->all();
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

<div>
    <x-section heading="Availability" collapsible>
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
                        <span class="text-zinc-500">&middot;</span>
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
                    <span class="text-zinc-500">&middot;</span>
                @endif

                @if (count($this->serverDisplayData) > 0)
                    <div class="flex items-center gap-1.5 text-zinc-400">
                        <x-plex-icon class="size-4" />
                        @foreach ($this->serverDisplayData as $server)
                            @if (! $loop->first)
                                <span class="text-zinc-500">&middot;</span>
                            @endif

                            <div class="flex items-center gap-1.5" wire:key="server-{{ $server['clientIdentifier'] }}">
                                <flux:avatar
                                    size="xs"
                                    circle
                                    :src="$server['ownerThumb']"
                                    :name="$server['name']"
                                    :tooltip="$server['tooltip']"
                                />
                                <span>{{ $server['episodeCount'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex items-center gap-1.5 text-zinc-400">
                        <x-plex-icon class="size-4" />
                        <span class="text-sm font-semibold">Unavailable</span>
                    </div>
                @endif
            </div>
        </x-slot>

        @if (count($this->serverDisplayData) > 0)
            <flux:table class="mt-4">
                <flux:table.rows>
                    @foreach ($this->serverDisplayData as $server)
                        <flux:table.row wire:key="row-{{ $server['clientIdentifier'] }}">
                            <flux:table.cell variant="strong">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="{{ $server['isOnline'] ? 'bg-green-500' : 'bg-red-500' }} size-2 shrink-0 rounded-full"
                                    ></div>
                                    <flux:avatar
                                        size="xs"
                                        circle
                                        :src="$server['ownerThumb']"
                                        :name="$server['name']"
                                    />
                                    {{ $server['name'] }}
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($server['hasAllAired'])
                                    All
                                @else
                                    {{ $server['episodeCount'] }}
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="arrow-top-right-on-square"
                                    href="{{ $server['webUrl'] }}"
                                    target="_blank"
                                    inset="top bottom"
                                />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @else
            <flux:text class="mt-4 text-zinc-500">Not available on any Plex server.</flux:text>
        @endif

        @if (count($this->episodeMilestones) > 0)
            <flux:separator class="my-4" />
            <flux:heading size="xs" class="mb-2 text-zinc-400">Episodes</flux:heading>
            <flux:table>
                <flux:table.rows>
                    @foreach ($this->episodeMilestones as $key => $milestone)
                        <flux:table.row wire:key="milestone-{{ $key }}">
                            <flux:table.cell variant="strong">
                                {{ $milestone['label'] }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <span class="text-sm text-zinc-400">{{ $milestone['code'] }}</span>
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $milestone['name'] ?? '' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <span class="text-sm text-zinc-400">
                                    @if ($milestone['date'])
                                        {{ $milestone['date'] }}
                                    @endif

                                    @if ($milestone['date'] && $milestone['runtime'])
                                        &middot;
                                    @endif

                                    @if ($milestone['runtime'])
                                        {{ $milestone['runtime'] }}
                                    @endif
                                </span>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-section>
</div>
