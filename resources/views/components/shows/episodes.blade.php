<?php

use App\Jobs\StoreShowEpisodes;
use App\Models\Show;
use App\Services\CartService;
use App\Services\TVMazeService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
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
            <p>Loading...</p>
        </div>
        HTML;
    }

    public function mount(?Collection $episodes = null): void
    {
        if ($episodes !== null && $episodes->isNotEmpty()) {
            $this->episodes = $episodes;
        } else {
            $tvMaze = app(TVMazeService::class);

            try {
                $apiEpisodes = $tvMaze->episodes($this->show->tvmaze_id);
            } catch (RequestException $e) {
                $this->error = $e->response->status() === 404 ? null : 'Failed to load episodes from TVMaze.';
                $apiEpisodes = [];
            }

            if (! empty($apiEpisodes)) {
                StoreShowEpisodes::dispatch($this->show, $apiEpisodes);
            }

            $this->episodes = collect($apiEpisodes);
        }
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
                    ]
                    : [
                        'id' => $ep->id,
                        'tvmaze_id' => $ep->tvmaze_id ?? $ep->id,
                        'season' => $ep->season,
                        'number' => $ep->number,
                        'name' => $ep->name,
                        'type' => $ep->type ?? 'regular',
                        'airdate' => $ep->airdate?->format('Y-m-d'),
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
                if (isset($selected[strtoupper($code)])) {
                    $orderedCodes[] = $code;
                }
            }
        }

        app(CartService::class)->syncShowEpisodes($this->show->id, $orderedCodes);
        $this->dispatch('cart-updated');
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
    }"
>
    <flux:heading size="lg" class="mt-8">Episodes</flux:heading>

    @forelse ($this->seasons as $season)
        <div x-init="initSeason('{{ $season['number'] }}')" wire:key="season-{{ $season['number'] }}">
            <flux:checkbox.group
                x-model="seasonSelections['{{ $season['number'] }}']"
                @change="scheduleSync()"
                class="mt-4"
            >
                <flux:checkbox.all label="S{{ str_pad($season['number'], 2, '0', STR_PAD_LEFT) }}" />

                <div class="mt-2 ml-6 space-y-1">
                    @foreach ($season['episodes'] as $episode)
                        <div
                            wire:key="episode-{{ $episode['id'] ?? $episode['tvmaze_id'] }}"
                            class="flex items-center gap-3"
                        >
                            <flux:checkbox value="{{ \App\Models\Episode::displayCode($episode) }}" />
                            <span class="flex-1">
                                {{ \App\Models\Episode::displayCode($episode) }} {{ $episode['name'] }} -
                                {{ $episode['airdate'] }}
                            </span>
                            @php($epServers = $this->plexAvailability[\App\Models\Episode::displayCode($episode)] ?? [])
                            @if (count($epServers) > 0)
                                <div class="flex gap-1">
                                    @foreach ($epServers as $server)
                                        <flux:badge size="sm" color="green">{{ $server['name'] }}</flux:badge>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </flux:checkbox.group>
        </div>
    @empty
        @if ($error)
            <flux:callout variant="danger" class="mt-4">
                <flux:callout.heading>Error</flux:callout.heading>
                <flux:callout.text>{{ $error }}</flux:callout.text>
            </flux:callout>
        @else
            <p>No episodes available.</p>
        @endif
    @endforelse
</div>
