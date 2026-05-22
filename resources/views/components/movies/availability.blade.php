<?php

use App\Models\Movie;
use App\Models\PlexMediaServer;
use App\Services\ThirdParty\PlexService;
use App\Support\Formatters;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Movie $movie;

    public bool $plexLoaded = false;

    public function loadPlex(): void
    {
        $this->plexLoaded = true;
    }

    #[Computed]
    public function servers(): Collection
    {
        if (! $this->plexLoaded) {
            return collect();
        }

        $user = auth()->user();
        if (! $user?->plex_token) {
            return collect();
        }

        return Cache::remember("plex:movie:{$user->id}:{$this->movie->id}", now()->addMinutes(10), function () use (
            $user,
        ) {
            $plex = app(PlexService::class);
            $externalGuid = "imdb://{$this->movie->imdb_id}";

            return $plex->searchByExternalId($user->plex_token, $externalGuid, 1);
        });
    }

    #[Computed]
    public function releaseData(): Collection
    {
        return $this->movie->releaseDatesByCountry();
    }

    /**
     * @return list<array{name: string, clientIdentifier: string, ownerThumb: string|null, isOnline: bool, videoResolution: string|null, runtime: string|null, tooltip: string, webUrl: string}>
     */
    #[Computed]
    public function serverDisplayData(): array
    {
        if ($this->servers->isEmpty()) {
            return [];
        }

        $clientIds = $this->servers->pluck('clientIdentifier')->all();
        $plexServers = PlexMediaServer::where('visible', true)
            ->whereIn('client_identifier', $clientIds)
            ->get()
            ->keyBy('client_identifier');

        return $this->servers
            ->filter(fn (array $server): bool => $plexServers->has($server['clientIdentifier']))
            ->map(function (array $server) use ($plexServers): array {
                $ratingKey = $server['match']['ratingKey'] ?? '';
                $webUrl = "https://app.plex.tv/desktop/#!/server/{$server['clientIdentifier']}/details?key=%2Flibrary%2Fmetadata%2F{$ratingKey}";
                $resolution = $server['match']['Media'][0]['videoResolution'] ?? null;
                $durationMs = $server['match']['duration'] ?? null;
                $runtime = $durationMs ? Formatters::runtime((int) round($durationMs / 60000)) : null;

                return [
                    'name' => $server['name'],
                    'clientIdentifier' => $server['clientIdentifier'],
                    'ownerThumb' => $plexServers->get($server['clientIdentifier'])->owner_thumb,
                    'isOnline' => $plexServers->get($server['clientIdentifier'])->is_online,
                    'videoResolution' => Formatters::formatResolution($resolution),
                    'runtime' => $runtime,
                    'tooltip' => $server['name'],
                    'webUrl' => $webUrl,
                ];
            })
            ->all();
    }
};
?>

<div wire:init="loadPlex">
    <x-section collapsible>
        <x-slot:badge>
            <div class="flex items-center gap-1.5 text-sm">
                @php
                    $releaseYear = $movie->release_date?->year ?? $movie->year;
                    $hasPrevious = false;
                @endphp

                @if ($releaseYear)
                    <span class="text-zinc-300">{{ $releaseYear }}</span>
                    @php
                        $hasPrevious = true;
                    @endphp
                @endif

                @if ($movie->status)
                    @if ($hasPrevious)
                        <x-middot />
                    @endif

                    <x-dynamic-component
                        :component="'flux::icon.' . $movie->status->icon()"
                        variant="micro"
                        :class="$movie->status->iconColorClass()"
                    />
                    <span class="{{ $movie->status->iconColorClass() }} text-xs">
                        {{ $movie->status->getLabel() }}
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

                        <span
                            class="block truncate font-serif tracking-wide sm:inline sm:overflow-visible sm:whitespace-normal"
                        >
                            {{ $server['name'] }}
                        </span>
                        @if ($server['videoResolution'] || $server['runtime'])
                            <span class="hidden text-zinc-500 sm:inline">·</span>
                            <span class="block text-sm text-zinc-400 sm:inline">
                                @if ($server['videoResolution'])
                                    {{ $server['videoResolution'] }}
                                @endif

                                @if ($server['videoResolution'] && $server['runtime'])
                                    <span class="text-zinc-500">·</span>
                                @endif

                                @if ($server['runtime'])
                                    {{ $server['runtime'] }}
                                @endif
                            </span>
                        @endif

                        <x-slot:trailing>
                            <flux:icon name="arrow-top-right-on-square" variant="mini" class="shrink-0 text-zinc-400" />
                        </x-slot>
                    </x-dashboard.list-row>
                @endforeach
            </x-dashboard.list>
        @else
            <flux:text class="mt-4 text-zinc-500">Not available on any Plex server.</flux:text>
        @endif

        @if ($this->releaseData->isNotEmpty())
            @php
                $allReleases = $this->releaseData
                    ->flatMap(fn ($group) => collect($group['releases'])->map(fn ($release) => array_merge($release, ['country' => $group['country']])))
                    ->sortBy('date')
                    ->values();
            @endphp

            <flux:separator class="my-4" />
            <x-dashboard.list>
                @foreach ($allReleases as $release)
                    @php
                        $flag = collect(str_split(strtoupper($release['country'])))
                            ->map(fn ($c) => mb_chr(ord($c) - ord('A') + 0x1f1e6))
                            ->join('');
                    @endphp

                    <x-dashboard.list-row
                        class="text-sm"
                        :wire-key="'release-' . $release['country'] . '-' . $release['type']->value"
                    >
                        <span class="block truncate sm:inline sm:overflow-visible sm:whitespace-normal">
                            {{ $flag }} {{ $release['type']->label() }}
                        </span>
                        <span class="hidden text-zinc-500 sm:inline">·</span>
                        <span class="block text-sm text-zinc-400 sm:inline">
                            {{ $release['date']->format('M j, Y') }}
                        </span>

                        @if ($release['certification'] || $release['note'] || ! empty($release['descriptors']))
                            <x-slot:trailing>
                                <div
                                    class="flex shrink-0 flex-wrap items-center justify-end gap-2 text-sm text-zinc-400"
                                >
                                    @if ($release['certification'])
                                        <x-rating-badge :certification="$release['certification']" />
                                    @endif

                                    @if ($release['note'])
                                        <span class="text-zinc-500">{{ $release['note'] }}</span>
                                    @endif

                                    @if (! empty($release['descriptors']))
                                        <span class="text-zinc-500">
                                            {{ implode(', ', $release['descriptors']) }}
                                        </span>
                                    @endif
                                </div>
                            </x-slot>
                        @endif
                    </x-dashboard.list-row>
                @endforeach
            </x-dashboard.list>
        @endif
    </x-section>
</div>
