<?php

use App\Actions\Tv\PrepareEpisodesForDisplay;
use App\Actions\Tv\UpsertEpisodes;
use App\Models\Show;
use App\Services\CartService;
use App\Services\TVMazeService;
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

    /** @var array<string, array<int, array{name: string, owned: bool}>> */
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
            <flux:skeleton />
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
            if ($e->response->status() !== 404) {
                Cache::put($cacheKey, true, now()->addHour());
                $this->error = __('lundbergh.error.episodes_backoff');
            }
            $apiEpisodes = [];
        }

        if (! empty($apiEpisodes)) {
            app(UpsertEpisodes::class)->fromApi($this->show, $apiEpisodes);
        }

        $this->episodes = collect(app(PrepareEpisodesForDisplay::class)->prepare($apiEpisodes));
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
        $today = today();
        $expandedSeason = null;

        foreach ($this->seasons as $season) {
            foreach ($season['episodes'] as $episode) {
                $airdate = is_array($episode) ? $episode['airdate'] ?? null : $episode->airdate;
                if (! empty($airdate) && Carbon::parse($airdate)->lte($today)) {
                    $expandedSeason = $season['number'];

                    break;
                }
            }
        }

        return $expandedSeason ?? ($this->seasons->keys()->first() ?? 0);
    }

    /**
     * Sync selected episodes to cart in display order (by season and episode number).
     *
     * @param  array<int, string>  $episodeCodes  Unordered codes from Alpine
     */
    public function syncToCart(array $episodeCodes): void
    {
        $selected = array_flip(array_map('strtoupper', $episodeCodes));

        $orderedCodes = [];
        foreach ($this->seasons as $season) {
            foreach ($season['episodes'] as $episode) {
                $code = \App\Models\Episode::displayCode($episode);
                if (isset($selected[strtoupper($code)]) && $this->hasAired($episode)) {
                    $orderedCodes[] = $code;
                }
            }
        }

        $previousCodes = collect(app(CartService::class)->episodes())
            ->where('show_id', $this->show->id)
            ->pluck('code')
            ->map(fn ($c) => strtolower($c))
            ->sort()
            ->values()
            ->all();

        app(CartService::class)->syncShowEpisodes($this->show->id, $orderedCodes);
        $this->dispatch('cart-updated');

        $newCodes = collect($orderedCodes)
            ->map(fn ($c) => strtolower($c))
            ->sort()
            ->values()
            ->all();

        if ($previousCodes === $newCodes) {
            return;
        }

        $delta = count($orderedCodes) - count($previousCodes);

        if ($delta > 0) {
            Flux::toast(
                text: trans_choice('lundbergh.toast.episodes_added', $delta, [
                    'title' => $this->show->name,
                ]),
            );
        } elseif ($delta < 0) {
            Flux::toast(
                text: trans_choice('lundbergh.toast.episodes_removed', abs($delta), [
                    'title' => $this->show->name,
                ]),
            );
        } else {
            Flux::toast(
                text: __('lundbergh.toast.episodes_swapped', [
                    'title' => $this->show->name,
                ]),
            );
        }
    }

    /**
     * Get initial season selections from cart for this show.
     *
     * @return array<string, array<int, string>>
     */
    #[Computed]
    public function initialSeasonSelections(): array
    {
        $cartEpisodes = app(CartService::class)->episodes();

        $showEpisodes = collect($cartEpisodes)
            ->filter(fn ($ep) => $ep['show_id'] === $this->show->id)
            ->pluck('code')
            ->map(fn ($code) => strtoupper($code));

        $result = [];
        foreach ($showEpisodes as $code) {
            if (preg_match('/^S(\d+)[ES]\d+$/i', $code, $matches)) {
                $season = (string) (int) $matches[1];
                if (! isset($result[$season])) {
                    $result[$season] = [];
                }
                $result[$season][] = $code;
            }
        }

        return $result;
    }

    public function hasAired(mixed $episode): bool
    {
        $airdate = is_array($episode) ? $episode['airdate'] ?? null : $episode->airdate;

        if (empty($airdate)) {
            return false;
        }

        return Carbon::parse($airdate)->lte(today());
    }

    public function pad(int $number): string
    {
        return str_pad((string) $number, 2, '0', STR_PAD_LEFT);
    }

    public function formatRuntime(int $minutes): string
    {
        if ($minutes < 60) {
            return $this->pad($minutes) . 'm';
        }

        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        return $remainder > 0 ? "{$hours}h" . $this->pad($remainder) . 'm' : "{$hours}h";
    }
};
?>

<div
    x-data="{
        seasonSelections: @js($this->initialSeasonSelections),
        syncTimeout: null,
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
            window.dispatchEvent(new CustomEvent('cart-syncing'))
            this.syncTimeout = setTimeout(() => {
                $wire.syncToCart(this.selected)
            }, 500)
        },
        seasonCount(num, total) {
            const selected = this.seasonSelections[num]?.length || 0
            return selected > 0
                ? String(selected).padStart(2, '0') + '/' + total
                : total
        },
    }"
>
    <div class="space-y-2">
        @forelse ($this->seasons as $season)
            @if (! $loop->first)
                <flux:separator />
            @endif

            <div x-init="initSeason('{{ $season['number'] }}')" wire:key="season-{{ $season['number'] }}">
                <flux:checkbox.group x-model="seasonSelections['{{ $season['number'] }}']" @change="scheduleSync()">
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
                                                        {{ \App\Models\Episode::displayCode($episode) }}
                                                    </span>
                                                    <span class="font-serif">{{ $episode['name'] }}</span>
                                                </span>
                                                <span class="ml-auto shrink-0 font-mono text-sm text-zinc-500">
                                                    @if ($episode['runtime'] ?? null)
                                                        {{ $this->formatRuntime($episode['runtime']) }}
                                                    @endif

                                                    @if ($episode['airdate'])
                                                        @if ($episode['runtime'] ?? null)
                                                            &middot;
                                                        @endif

                                                        {{ Carbon::parse($episode['airdate'])->format('m/d/y') }}
                                                    @endif
                                                </span>
                                            </div>
                                            <div class="ms-2 size-5 shrink-0"></div>
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
</div>
