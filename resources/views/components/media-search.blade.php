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
            ->map(function (Movie|Show $item): array {
                $isShow = $item instanceof Show;

                return [
                    'type' => $isShow ? 'show' : 'movie',
                    'id' => $item->id,
                    'title' => $isShow ? $item->name : $item->title,
                    'year' => $isShow ? $item->premiered?->year : $item->year,
                    'genres' => $item->genres ? implode(', ', $item->genres) : null,
                    'poster_url' => $item->artUrl('poster', true),
                    'model' => $isShow ? null : $item,
                ];
            });
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
                </x-slot>

                @foreach ($this->results() as $result)
                    <flux:command.item
                        wire:key="search-result-{{ $result['type'] }}-{{ $result['id'] }}"
                        wire:click="selectResult('{{ $result['type'] }}', {{ $result['id'] }})"
                    >
                        <div class="flex w-full items-center gap-3">
                            <div class="h-12 w-9 shrink-0 overflow-hidden rounded-md bg-zinc-700">
                                @if ($result['poster_url'])
                                    <img
                                        src="{{ $result['poster_url'] }}"
                                        alt="{{ $result['title'] }} poster"
                                        class="h-full w-full object-cover"
                                        loading="lazy"
                                    />
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-zinc-500">
                                        <flux:icon
                                            name="{{ $result['type'] === 'show' ? 'tv' : 'film' }}"
                                            class="size-5"
                                        />
                                    </div>
                                @endif
                            </div>

                            <div class="flex min-w-0 flex-1 items-center justify-between gap-2">
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
                        </div>
                    </flux:command.item>
                @endforeach
            </flux:command.items>

            @if (strlen($query) >= 2 && ! SearchService::isImdbId($query))
                <div class="p-2">
                    <flux:callout>
                        <flux:callout.text>
                            {!!
                                __('lundbergh.empty.search_imdb_hint', [
                                    'imdb_link' => '<a href="https://www.imdb.com" target="_blank" class="inline font-medium underline underline-offset-[6px] hover:decoration-current decoration-white/20">IMDb</a>',
                                ])
                            !!}
                        </flux:callout.text>
                    </flux:callout>
                </div>
            @endif
        </flux:command>
    </flux:modal>
</div>
