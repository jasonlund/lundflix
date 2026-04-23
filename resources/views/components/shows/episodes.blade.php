<?php

use App\Actions\TVMaze\UpsertTVMazeEpisodes;
use App\Enums\EpisodeType;
use App\Models\Show;
use App\Services\ThirdParty\TVMazeService;
use App\Support\AirDateTime;
use App\Support\Formatters;
use App\Support\UserTime;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public Show $show;

    /** @var Collection<int, mixed> */
    public $episodes;

    public ?string $error = null;

    /** @var array<string, list<array{name: string, clientIdentifier: string, ownerThumb: string|null, isOnline: bool, videoResolution: string|null, duration: int|null, webUrl: string}>> */
    public array $plexAvailability = [];

    #[On('plex-show-loaded')]
    public function setPlexAvailability(array $availability): void
    {
        $this->plexAvailability = $availability;
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div>
            <x-lundflix-skeleton />
        </div>
        HTML;
    }

    public function mount(?Collection $episodes = null): void
    {
        if ($episodes !== null && $episodes->isNotEmpty()) {
            $this->episodes = $episodes->map(
                fn ($ep) => is_array($ep)
                    ? $ep
                    : [
                        'id' => $ep->id,
                        'tvmaze_id' => $ep->tvmaze_id ?? $ep->id,
                        'season' => $ep->season,
                        'number' => $ep->number,
                        'name' => $ep->name,
                        'type' => $ep->type ?? 'regular',
                        'airdate' => $ep->airdate?->format('Y-m-d'),
                        'airtime' => $ep->airtime ?? null,
                    ],
            );

            return;
        }

        $cacheKey = "tvmaze:episodes-failure:{$this->show->tvmaze_id}";

        if (Cache::has($cacheKey)) {
            $this->error = __('lundbergh.error.episodes_backoff');
            $this->episodes = collect();

            return;
        }

        $tvMaze = app(TVMazeService::class);

        try {
            $apiEpisodes = $tvMaze->episodes($this->show->tvmaze_id);
        } catch (RequestException $e) {
            if ($e->response->status() === 404) {
                $this->error = null;
            } else {
                $this->error = __('lundbergh.error.episodes_backoff');
                Cache::put($cacheKey, true, now()->addHour());
            }

            $this->episodes = collect();

            return;
        }

        if (! empty($apiEpisodes)) {
            app(UpsertTVMazeEpisodes::class)->fromApi($this->show, $apiEpisodes);
            $this->dispatch('episodes-loaded');
        }

        $this->episodes = collect($apiEpisodes)
            ->reject(fn (array $ep): bool => ($ep['type'] ?? 'regular') === EpisodeType::InsignificantSpecial->value)
            ->values();
    }

    public function dehydrate(): void
    {
        if ($this->episodes === null) {
            return;
        }

        $this->episodes = $this->episodes
            ->map(
                fn ($ep) => is_array($ep)
                    ? [
                        'id' => $ep['id'] ?? null,
                        'tvmaze_id' => $ep['tvmaze_id'] ?? ($ep['id'] ?? null),
                        'season' => $ep['season'],
                        'number' => $ep['number'] ?? 1,
                        'name' => $ep['name'] ?? '',
                        'type' => $ep['type'] ?? 'regular',
                        'airdate' => $ep['airdate'] ?? null,
                        'airtime' => $ep['airtime'] ?? null,
                        'runtime' => $ep['runtime'] ?? null,
                    ]
                    : [
                        'id' => $ep->id,
                        'tvmaze_id' => $ep->tvmaze_id ?? $ep->id,
                        'season' => $ep->season,
                        'number' => $ep->number,
                        'name' => $ep->name,
                        'type' => $ep->type ?? 'regular',
                        'airdate' => $ep->airdate?->format('Y-m-d'),
                        'airtime' => $ep->airtime ?? null,
                        'runtime' => $ep->runtime,
                    ],
            )
            ->all();
    }

    public function hydrate(): void
    {
        $this->episodes = collect($this->episodes);
    }

    /**
     * @return Collection<int, array{number: int, codes: array<int, string>, episodes: Collection<int, mixed>}>
     */
    #[Computed]
    public function seasons(): Collection
    {
        $episodes = $this->episodes instanceof Collection ? $this->episodes : collect($this->episodes ?? []);

        return $episodes
            ->groupBy(fn ($ep) => is_array($ep) ? $ep['season'] : $ep->season)
            ->sortKeys()
            ->map(
                fn ($episodes, $seasonNumber) => [
                    'number' => $seasonNumber,
                    'codes' => $episodes
                        ->map(fn ($ep) => \App\Models\Episode::displayCode($ep))
                        ->values()
                        ->all(),
                    'episodes' => $episodes->sortBy(fn ($ep) => is_array($ep) ? $ep['number'] : $ep->number),
                ],
            );
    }

    /**
     * Determine which season should be expanded by default.
     *
     * Returns the most recent season with at least one aired episode,
     * or the first season if no episodes have aired yet.
     */
    #[Computed]
    public function expandedSeason(): int|string
    {
        $expandedSeason = null;

        foreach ($this->seasons as $season) {
            foreach ($season['episodes'] as $episode) {
                $airdate = is_array($episode) ? $episode['airdate'] ?? null : $episode->airdate;
                $airtime = is_array($episode) ? $episode['airtime'] ?? null : $episode->airtime;
                if (
                    ! empty($airdate) &&
                    AirDateTime::hasAired($airdate, $airtime, $this->show->web_channel, $this->show->network)
                ) {
                    $expandedSeason = $season['number'];

                    break;
                }
            }
        }

        return $expandedSeason ?? ($this->seasons->keys()->first() ?? 0);
    }

    public function hasAired(mixed $episode): bool
    {
        $airdate = is_array($episode) ? $episode['airdate'] ?? null : $episode->airdate;

        if (empty($airdate)) {
            return false;
        }

        $airtime = is_array($episode) ? $episode['airtime'] ?? null : $episode->airtime;

        return AirDateTime::hasAired($airdate, $airtime, $this->show->web_channel, $this->show->network);
    }

    public function pad(int $number): string
    {
        return str_pad((string) $number, 2, '0', STR_PAD_LEFT);
    }
};
?>

<div
    x-data="{
        seasonSelections: {},
        syncTimeout: null,
        init() {
            // Initialize seasonSelections from Alpine cart store
            const eps = $store.cart.episodes.filter(
                (e) => e.show_id === {{ $show->id }},
            )
            eps.forEach((e) => {
                const m = e.code.match(/^s(\d+)/)
                if (m) {
                    const s = String(parseInt(m[1]))
                    if (! this.seasonSelections[s]) this.seasonSelections[s] = []
                    this.seasonSelections[s].push(e.code.toUpperCase())
                }
            })
        },
        get selected() {
            return Object.values(this.seasonSelections).flat()
        },
        initSeason(num) {
            if (! this.seasonSelections[num]) {
                this.seasonSelections[num] = []
            }
        },
        scheduleSync() {
            clearTimeout(this.syncTimeout)
            this.syncTimeout = setTimeout(() => {
                const codes = this.selected
                const prevCodes = $store.cart.episodes
                    .filter((e) => e.show_id === {{ $show->id }})
                    .map((e) => e.code.toUpperCase())
                    .sort()
                const newCodes = [...codes].sort()

                if (
                    prevCodes.length === newCodes.length &&
                    prevCodes.every((c, i) => c === newCodes[i])
                ) {
                    return
                }

                const delta = newCodes.length - prevCodes.length
                $store.cart.syncShowEpisodes({{ $show->id }}, codes)

                let toastKey = 'episodes_swapped'
                if (delta > 0) toastKey = 'episodes_added'
                else if (delta < 0) toastKey = 'episodes_removed'

                $dispatch('cart-episodes-synced', {
                    showId: {{ $show->id }},
                    showName: {{ Js::from($show->name) }},
                    delta: Math.abs(delta),
                    toastKey: toastKey,
                })
            }, 1000)
        },
        seasonCount(num, total) {
            const selected = this.seasonSelections[num]?.length || 0
            return selected > 0
                ? String(selected).padStart(2, '0') + '/' + total
                : total
        },
        selectedPlexEpisode: null,
        openPlex(code, name) {
            const servers = $wire.plexAvailability[code]
            if (! servers || servers.length === 0) return
            if (servers.length === 1) {
                window.open(servers[0].webUrl, '_blank')
            } else {
                this.selectedPlexEpisode = { code, name, servers }
                $flux.modal('plex-episode-servers').show()
            }
        },
        oxfordComma(items) {
            if (items.length <= 1) return items[0] || ''
            if (items.length === 2) return items[0] + ' and ' + items[1]
            return (
                items.slice(0, -1).join(', ') + ', and ' + items[items.length - 1]
            )
        },
    }"
>
    <x-section heading="Episodes">
        <div class="mt-2 space-y-2">
            @forelse ($this->seasons as $season)
                @if (! $loop->first)
                    <flux:separator />
                @endif

                <div x-init="initSeason('{{ $season['number'] }}')" wire:key="season-{{ $season['number'] }}">
                    <flux:checkbox.group
                        x-model="seasonSelections['{{ $season['number'] }}']"
                        @change="scheduleSync()"
                    >
                        <flux:accordion transition>
                            <flux:accordion.item :expanded="$season['number'] === $this->expandedSeason">
                                <flux:accordion.heading>
                                    <div class="flex w-full items-center gap-2">
                                        <div x-on:click.stop>
                                            <flux:checkbox.all />
                                        </div>
                                        <span class="font-mono">S{{ $this->pad($season['number']) }}</span>
                                        <span
                                            class="ml-auto font-mono text-sm text-zinc-500"
                                            x-text="
                                                seasonCount(
                                                    '{{ $season['number'] }}',
                                                    '{{ $this->pad(count($season['episodes'])) }}',
                                                )
                                            "
                                        ></span>
                                    </div>
                                </flux:accordion.heading>

                                <flux:accordion.content>
                                    <div class="space-y-1">
                                        @foreach ($season['episodes'] as $episode)
                                            <div
                                                wire:key="episode-{{ $episode['id'] ?? $episode['tvmaze_id'] }}"
                                                @class(['flex items-center', 'cursor-not-allowed opacity-50' => ! $this->hasAired($episode)])
                                            >
                                                <div class="flex min-w-0 flex-1 items-center gap-2">
                                                    @if ($this->hasAired($episode))
                                                        <flux:checkbox
                                                            value="{{ \App\Models\Episode::displayCode($episode) }}"
                                                        />
                                                    @else
                                                        <div
                                                            class="size-[1.125rem] shrink-0 rounded-[.3rem] border border-white/10"
                                                        ></div>
                                                    @endif
                                                    <span class="min-w-0 truncate">
                                                        <span class="font-mono">
                                                            <span class="hidden sm:inline">
                                                                {{ \App\Models\Episode::displayCode($episode) }}
                                                            </span>
                                                            <span class="sm:hidden">
                                                                {{ preg_replace('/^S\d+/', '', \App\Models\Episode::displayCode($episode)) }}
                                                            </span>
                                                        </span>
                                                        <span class="font-serif tracking-wide">
                                                            {{ $episode['name'] }}
                                                        </span>
                                                    </span>
                                                    <span class="ml-auto shrink-0 font-mono text-sm text-zinc-500">
                                                        @if ($episode['runtime'] ?? null)
                                                            {{ Formatters::runtime($episode['runtime']) }}
                                                        @endif

                                                        @if ($episode['airdate'])
                                                            @if ($episode['runtime'] ?? null)
                                                                &middot;
                                                            @endif

                                                            {{ UserTime::format(AirDateTime::resolve($episode['airdate'], $episode['airtime'] ?? null, $this->show->web_channel, $this->show->network)) }}
                                                        @endif
                                                    </span>
                                                </div>
                                                <div class="ms-2 size-5 shrink-0">
                                                    @if (isset($plexAvailability[\App\Models\Episode::displayCode($episode)]))
                                                        <button
                                                            type="button"
                                                            x-on:click.stop="
                                                                openPlex(
                                                                    '{{ \App\Models\Episode::displayCode($episode) }}',
                                                                    {{ Js::from($episode['name']) }},
                                                                )
                                                            "
                                                            class="cursor-pointer text-emerald-500 transition-colors hover:text-emerald-400"
                                                        >
                                                            <flux:icon.check class="size-5" />
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </flux:accordion.content>
                            </flux:accordion.item>
                        </flux:accordion>
                    </flux:checkbox.group>
                </div>
            @empty
                <flux:callout :variant="$error ? 'danger' : null" class="mt-4">
                    <flux:callout.text>{{ $error ?? __('lundbergh.empty.episodes') }}</flux:callout.text>
                </flux:callout>
            @endforelse
        </div>
    </x-section>

    @teleport('body')
        <flux:modal name="plex-episode-servers" size="sm" :headless="true">
            <div x-show="selectedPlexEpisode" x-cloak>
                <p class="text-sm text-zinc-300">
                    {{ __('lundbergh.plex.multi_server_intro') }}
                    <span class="font-mono" x-text="selectedPlexEpisode?.code"></span>
                    (
                    <span class="font-serif tracking-wide" x-text="selectedPlexEpisode?.name"></span>
                    )
                    {{ __('lundbergh.plex.multi_server_middle') }}
                    <span x-text="oxfordComma(selectedPlexEpisode?.servers?.map((s) => s.name) ?? [])"></span>
                    ,
                    {{ __('lundbergh.plex.multi_server_outro') }}
                </p>

                <div class="mt-4 divide-y divide-zinc-700">
                    <template x-for="server in selectedPlexEpisode?.servers ?? []" :key="server.clientIdentifier">
                        <div class="flex items-center justify-between py-2.5">
                            <div class="flex items-center gap-2">
                                <div
                                    class="size-2 shrink-0 rounded-full"
                                    x-bind:class="server.isOnline ? 'bg-green-500' : 'bg-red-500'"
                                ></div>
                                <img
                                    x-bind:src="server.ownerThumb"
                                    x-bind:alt="server.name"
                                    class="size-6 rounded-full object-cover"
                                    x-show="server.ownerThumb"
                                />
                                <span
                                    x-show="!server.ownerThumb"
                                    class="flex size-6 items-center justify-center rounded-full bg-zinc-600 text-xs font-medium text-white"
                                    x-text="server.name?.charAt(0)?.toUpperCase()"
                                ></span>
                                <span class="text-sm font-medium text-white" x-text="server.name"></span>
                                <span
                                    x-show="server.videoResolution"
                                    class="text-xs text-zinc-400"
                                    x-text="server.videoResolution"
                                ></span>
                            </div>
                            <a
                                x-bind:href="server.webUrl"
                                target="_blank"
                                class="rounded-md p-1.5 text-zinc-400 transition-colors hover:bg-zinc-700 hover:text-white"
                            >
                                <flux:icon.arrow-top-right-on-square class="size-4" />
                            </a>
                        </div>
                    </template>
                </div>
            </div>
        </flux:modal>
    @endteleport
</div>
