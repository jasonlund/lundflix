<?php

use App\Jobs\StoreShowEpisodes;
use App\Models\Show;
use App\Services\TVMazeService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Lazy;
use Livewire\Component;

new #[Lazy] class extends Component {
    public Show $show;

    /** @var array<int, array<int, array>> */
    public array $episodesBySeason = [];

    public function mount(TVMazeService $tvMaze): void
    {
        // Check DB first
        $dbEpisodes = $this->show->episodes;

        if ($dbEpisodes->isNotEmpty()) {
            $this->episodesBySeason = $this->groupBySeason($dbEpisodes->toArray());

            return;
        }

        // Fetch from API
        $apiEpisodes = $tvMaze->episodes($this->show->tvmaze_id) ?? [];
        $this->episodesBySeason = $this->groupBySeason($apiEpisodes);

        // Queue storage
        if (! empty($apiEpisodes)) {
            StoreShowEpisodes::dispatch($this->show, $apiEpisodes);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $episodes
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function groupBySeason(array $episodes): array
    {
        return collect($episodes)
            ->groupBy('season')
            ->sortKeys()
            ->map(
                fn (Collection $eps) => $eps
                    ->sortBy('number')
                    ->values()
                    ->all(),
            )
            ->all();
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="mt-8">
            <flux:heading size="lg">Episodes</flux:heading>
            <div class="mt-4 animate-pulse space-y-2">
                <div class="h-8 w-32 rounded bg-zinc-700"></div>
                <div class="h-12 rounded bg-zinc-800"></div>
                <div class="h-12 rounded bg-zinc-800"></div>
                <div class="h-12 rounded bg-zinc-800"></div>
            </div>
        </div>
        HTML;
    }
};
?>

<div class="mt-8">
    <flux:heading size="lg">Episodes</flux:heading>

    @forelse ($episodesBySeason as $season => $episodes)
        <div class="mt-6" wire:key="season-{{ $season }}">
            <flux:heading size="md" class="mb-3">Season {{ $season }}</flux:heading>

            <div class="space-y-2">
                @foreach ($episodes as $episode)
                    <div
                        wire:key="episode-{{ $episode['tvmaze_id'] ?? $episode['id'] }}"
                        class="flex items-center gap-4 rounded-lg bg-zinc-800 p-3"
                    >
                        <div class="w-12 shrink-0 text-center">
                            <flux:text class="text-lg font-medium">{{ $episode['number'] ?? 'S' }}</flux:text>
                        </div>

                        <div class="min-w-0 flex-1">
                            <flux:text class="font-medium">{{ $episode['name'] }}</flux:text>
                            @if ($episode['airdate'])
                                <flux:text class="text-sm text-zinc-400">
                                    {{ \Carbon\Carbon::parse($episode['airdate'])->format('M j, Y') }}
                                </flux:text>
                            @endif
                        </div>

                        @if ($episode['runtime'])
                            <flux:text class="text-sm text-zinc-400">{{ $episode['runtime'] }} min</flux:text>
                        @endif

                        @php
                            // DB episodes have show_id, API episodes don't
                            $cartItem = isset($episode['show_id'])
                                ? \App\Models\Episode::find($episode['id'])
                                : array_merge($episode, ['show_id' => $show->id]);
                        @endphp

                        <livewire:cart.add-button
                            :item="$cartItem"
                            wire:key="add-btn-{{ $episode['tvmaze_id'] ?? $episode['id'] }}"
                        />
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <flux:text class="mt-4 text-zinc-400">No episodes available.</flux:text>
    @endforelse
</div>
