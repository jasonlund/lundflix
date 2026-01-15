<?php

use App\Models\Movie;
use App\Models\Show;
use App\Services\SearchService;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component {
    public string $query = '';

    public function results(): Collection
    {
        if (strlen($this->query) < 2) {
            return collect();
        }

        return app(SearchService::class)
            ->search($this->query)
            ->take(15)
            ->map(
                fn ($item) => [
                    'type' => $item instanceof Show ? 'show' : 'movie',
                    'id' => $item->id,
                    'title' => $item instanceof Show ? $item->name : $item->title,
                    'year' => $item instanceof Show ? $item->premiered?->year : $item->year,
                    'genres' => is_array($item->genres) ? implode(', ', $item->genres) : $item->genres,
                ],
            );
    }
};
?>

<div>
    <flux:modal name="search" variant="bare" class="my-[10vh] w-full max-w-xl">
        <flux:command :filter="false" class="border-none shadow-lg">
            <flux:command.input
                wire:model.live.debounce.300ms="query"
                placeholder="Search shows & movies..."
                autofocus
                clearable
                closable
            />
            <flux:command.items class="max-h-[60vh] overflow-y-auto">
                @forelse ($this->results() as $result)
                    <flux:command.item icon="{{ $result['type'] === 'show' ? 'tv' : 'film' }}">
                        <div class="flex flex-col">
                            <span>
                                {{ $result['title'] }}
                                @if ($result['year'])
                                    <span class="text-zinc-400">({{ $result['year'] }})</span>
                                @endif
                            </span>
                            @if ($result['genres'])
                                <span class="text-xs text-zinc-500">{{ $result['genres'] }}</span>
                            @endif
                        </div>
                    </flux:command.item>
                @empty
                    @if (strlen($query) >= 2)
                        <div class="px-4 py-8 text-center text-sm text-zinc-500">No results found</div>
                    @else
                        <div class="px-4 py-8 text-center text-sm text-zinc-500">Type to search shows & movies...</div>
                    @endif
                @endforelse
            </flux:command.items>
        </flux:command>
    </flux:modal>
</div>
