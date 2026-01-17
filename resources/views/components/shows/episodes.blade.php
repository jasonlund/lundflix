<?php

use App\Jobs\StoreShowEpisodes;
use App\Models\Episode;
use App\Models\Show;
use App\Services\TVMazeService;
use Illuminate\Database\Eloquent\Model;
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
        // Filter out insignificant specials
        $filtered = collect($episodes)->filter(fn (array $ep) => ($ep['type'] ?? 'regular') !== 'insignificant_special');

        // Assign display numbers to significant specials within each season
        $processed = $filtered->groupBy('season')->map(function (Collection $seasonEps) {
            // Separate regular and special episodes
            $regular = $seasonEps->filter(fn ($ep) => ($ep['type'] ?? 'regular') === 'regular');
            $specials = $seasonEps->filter(fn ($ep) => ($ep['type'] ?? 'regular') === 'significant_special');

            // Sort specials by airdate, then tvmaze_id, and assign numbers
            if ($specials->isNotEmpty()) {
                $specials = $specials
                    ->sortBy([['airdate', 'asc'], ['tvmaze_id', 'asc']])
                    ->values()
                    ->map(function ($ep, $index) {
                        // Only assign number if not already set (API episodes have null)
                        if ($ep['number'] === null) {
                            $ep['number'] = $index + 1;
                        }

                        return $ep;
                    });
            }

            // Merge and sort: regular first by number, then specials
            return $regular
                ->sortBy('number')
                ->values()
                ->concat($specials)
                ->all();
        });

        return $processed->sortKeys()->all();
    }

    /**
     * Get the cart item for an episode (Model for DB episodes, array for API episodes).
     *
     * @param  array<string, mixed>  $episode
     * @return Model|array<string, mixed>
     */
    public function cartItemFor(array $episode): Model|array
    {
        // DB episodes have show_id, API episodes don't
        if (isset($episode['show_id'])) {
            return Episode::find($episode['id']);
        }

        return array_merge($episode, ['show_id' => $this->show->id]);
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
                            @php
                                $isSpecial = ($episode['type'] ?? 'regular') === 'significant_special';
                            @endphp

                            <flux:text class="text-lg font-medium">
                                {{ $isSpecial ? 'S' : '' }}{{ $episode['number'] }}
                            </flux:text>
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

                        <livewire:cart.add-button
                            :item="$this->cartItemFor($episode)"
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
