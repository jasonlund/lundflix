<?php

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
        $episodes = $tvMaze->episodes($this->show->tvmaze_id) ?? [];

        $this->episodesBySeason = collect($episodes)
            ->groupBy('season')
            ->sortKeys()
            ->map(fn (Collection $eps) => $eps->sortBy('number')->values()->all())
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
                        wire:key="episode-{{ $episode['id'] }}"
                        class="flex items-center gap-4 rounded-lg bg-zinc-800 p-3"
                    >
                        <div class="w-12 shrink-0 text-center">
                            <flux:text class="text-lg font-medium">{{ $episode['number'] }}</flux:text>
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
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <flux:text class="mt-4 text-zinc-400">No episodes available.</flux:text>
    @endforelse
</div>
