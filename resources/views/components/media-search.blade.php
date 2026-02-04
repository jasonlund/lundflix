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
            ->take(10)
            ->map(
                fn ($item) => [
                    'type' => $item instanceof Show ? 'show' : 'movie',
                    'id' => $item->id,
                    'title' => $item instanceof Show ? $item->name : $item->title,
                    'year' => $item instanceof Show ? $item->premiered?->year : $item->year,
                    'genres' => $item->genres ? implode(', ', $item->genres) : null,
                    'model' => $item instanceof Show ? null : $item,
                ],
            );
    }

    public function selectResult(string $type, int $id): void
    {
        $route = $type === 'show' ? route('shows.show', $id) : route('movies.show', $id);
        $this->redirect($route, navigate: true);
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
                <x-slot:empty>
                    {{ strlen($query) >= 2 ? __('lundbergh.empty.search_no_results') : __('lundbergh.empty.search_prompt') }}
                </x-slot:empty>

                @foreach ($this->results() as $result)
                    <flux:command.item
                        wire:click="selectResult('{{ $result['type'] }}', {{ $result['id'] }})"
                        icon="{{ $result['type'] === 'show' ? 'tv' : 'film' }}"
                    >
                        <div class="flex w-full items-center justify-between gap-2">
                            <div class="flex min-w-0 flex-col">
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
                            <div @click.stop class="flex shrink-0 gap-1">
                                @if ($result['type'] === 'movie')
                                    <livewire:cart.add-movie-button
                                        :movie="$result['model']"
                                        :show-text="false"
                                        :wire:key="'search-cart-'.$result['id']"
                                    />
                                @endif

                                <flux:button
                                    as="a"
                                    href="{{ $result['type'] === 'show' ? route('shows.show', $result['id']) : route('movies.show', $result['id']) }}"
                                    wire:navigate
                                    icon="arrow-right"
                                    size="sm"
                                />
                            </div>
                        </div>
                    </flux:command.item>
                @endforeach
            </flux:command.items>
        </flux:command>
    </flux:modal>
</div>
