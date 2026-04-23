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

    #[Computed]
    public function servers(): Collection
    {
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

<div>
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
                        <span class="text-zinc-500">&middot;</span>
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
                                <span class="text-sm text-zinc-400">
                                    @if ($server['videoResolution'])
                                        {{ $server['videoResolution'] }}
                                    @endif

                                    @if ($server['videoResolution'] && $server['runtime'])
                                        &middot;
                                    @endif

                                    @if ($server['runtime'])
                                        {{ $server['runtime'] }}
                                    @endif
                                </span>
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

        @if ($this->releaseData->isNotEmpty())
            @php
                $allReleases = $this->releaseData
                    ->flatMap(fn ($group) => collect($group['releases'])->map(fn ($release) => array_merge($release, ['country' => $group['country']])))
                    ->sortBy('date')
                    ->values();
            @endphp

            <flux:separator class="my-4" />
            <flux:table>
                <flux:table.rows>
                    @foreach ($allReleases as $release)
                        @php
                            $flag = collect(str_split($release['country']))
                                ->map(fn ($c) => mb_chr(ord($c) - ord('A') + 0x1f1e6))
                                ->join('');
                        @endphp

                        <flux:table.row wire:key="release-{{ $release['country'] }}-{{ $release['type']->value }}">
                            <flux:table.cell variant="strong">
                                {{ $flag }} {{ $release['type']->label() }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $release['date']->format('M j, Y') }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-wrap items-center gap-2 text-sm text-zinc-400">
                                    @if ($release['certification'])
                                        <flux:badge size="sm" class="bg-white/10 backdrop-blur-sm">
                                            {{ $release['certification'] }}
                                        </flux:badge>
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
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-section>
</div>
