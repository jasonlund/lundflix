<?php

use App\Jobs\StoreShowEpisodes;
use App\Models\Show;
use App\Services\TVMazeService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public Show $show;

    public Collection $episodes;

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
            $apiEpisodes = $tvMaze->episodes($this->show->tvmaze_id) ?? [];

            if (! empty($apiEpisodes)) {
                StoreShowEpisodes::dispatch($this->show, $apiEpisodes);
            }

            $this->episodes = collect($apiEpisodes);
        }
    }

    /**
     * @return Collection<int, array{number: int, codes: array<int, string>, episodes: Collection<int, mixed>}>
     */
    #[Computed]
    public function seasons(): Collection
    {
        return $this->episodes
            ->groupBy(fn ($ep) => $ep['season'] ?? $ep->season)
            ->sortKeys()
            ->map(
                fn ($episodes, $seasonNumber) => [
                    'number' => $seasonNumber,
                    'codes' => $episodes
                        ->map(fn ($ep) => \App\Models\Episode::displayCode($ep))
                        ->values()
                        ->all(),
                    'episodes' => $episodes->sortBy(fn ($ep) => $ep['number'] ?? $ep->number),
                ],
            );
    }

    /**
     * Map of episode code to airdate for Alpine sorting.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function episodeAirdates(): array
    {
        return $this->episodes
            ->mapWithKeys(
                fn ($ep) => [
                    \App\Models\Episode::displayCode($ep) => $ep['airdate'] ?? $ep->airdate,
                ],
            )
            ->all();
    }
};
?>

<div
    x-data="{
        selected: [],
        airdates: @js($this->episodeAirdates),
        get sortedSelected() {
            return [...this.selected].sort((a, b) =>
                (this.airdates[a] || '').localeCompare(this.airdates[b] || ''),
            )
        },
    }"
>
    <flux:heading size="lg" class="mt-8">Episodes</flux:heading>

    @forelse ($this->seasons as $season)
        <flux:checkbox.group x-model="selected" class="mt-4" wire:key="season-{{ $season['number'] }}">
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
    @empty
        <p>No episodes available.</p>
    @endforelse
</div>
