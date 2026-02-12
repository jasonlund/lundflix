<?php

use App\Models\Movie;
use App\Models\Show;
use App\Services\SearchService;
use App\Support\Formatters;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $query = '';

    public string $language = 'en';

    #[Computed]
    public function results(): Collection
    {
        if (strlen($this->query) < 2) {
            return collect();
        }

        return app(SearchService::class)
            ->search($this->query, 'all', $this->language ?: null)
            ->take(5)
            ->map(function (Movie|Show $item): array {
                $isShow = $item instanceof Show;

                return [
                    'type' => $isShow ? 'show' : 'movie',
                    'id' => $item->id,
                    'title' => $isShow ? $item->name : $item->title,
                    'yearLabel' => Formatters::yearLabel($item),
                    'language' => $this->shouldShowLanguage()
                        ? ($isShow
                            ? $item->language?->getLabel()
                            : $item->original_language?->getLabel())
                        : null,
                    'status' => $isShow ? $item->status : null,
                    'runtime' => Formatters::runtimeFor($item),
                    'genres' => $item->genres ?? [],
                    'networkInfo' => $isShow ? $this->networkInfoFor($item) : [],
                    'model' => $item,
                ];
            });
    }

    /**
     * @return list<array{name: string, tooltip: string, logoUrl: string|null}>
     */
    private function networkInfoFor(Show $show): array
    {
        $items = [];

        if ($show->network) {
            $name = $show->network['name'];
            $tooltip = $name;
            if (isset($show->network['country']['name'])) {
                $tooltip .= ' (' . $this->abbreviateCountry($show->network['country']['name']) . ')';
            }

            $items[] = ['name' => $name, 'tooltip' => $tooltip, 'logoUrl' => $show->networkLogoUrl()];
        }

        if ($show->web_channel) {
            $name = $show->web_channel['name'];
            $items[] = ['name' => $name, 'tooltip' => $name, 'logoUrl' => $show->streamingLogoUrl()];
        }

        return $items;
    }

    private function abbreviateCountry(string $country): string
    {
        return match ($country) {
            'United States' => 'US',
            'United Kingdom' => 'UK',
            'Australia' => 'AU',
            default => $country,
        };
    }

    private function shouldShowLanguage(): bool
    {
        return SearchService::isImdbId($this->query) || in_array($this->language, ['foreign', '']);
    }

    public function clearSearch(): void
    {
        $this->query = '';
    }
};
?>

<div>
    <flux:modal
        name="search"
        variant="bare"
        class="m-0 h-dvh min-h-dvh w-full max-w-none p-0 md:mx-auto md:max-w-screen-md [&::backdrop]:bg-transparent"
    >
        <flux:command
            :filter="false"
            class="flex h-full w-full flex-col overflow-hidden rounded-none border-0 bg-zinc-900/25 shadow-none backdrop-blur-sm"
        >
            <div class="relative">
                <flux:command.input
                    wire:model.live.debounce.300ms="query"
                    placeholder="Search shows & movies and filter by langauge..."
                    autofocus
                    clearable
                    closable
                    @class([
                        'h-14 border-0 bg-zinc-900/75 ps-12 text-base backdrop-blur-sm',
                        'pe-36' => ! SearchService::isImdbId($query),
                        'pe-12' => SearchService::isImdbId($query),
                    ])
                />
                @if (! SearchService::isImdbId($query))
                    <div class="absolute inset-y-0 end-10 flex items-center">
                        <div
                            class="flex rounded-md bg-zinc-800 p-0.5 text-xs font-medium"
                            role="group"
                            aria-label="Language filter"
                        >
                            <flux:tooltip content="English" class="text-xs">
                                <button
                                    type="button"
                                    wire:click="$set('language', 'en')"
                                    @class([
                                        'rounded p-1.5 transition-colors',
                                        'bg-zinc-600 text-white' => $language === 'en',
                                        'text-zinc-400 hover:text-zinc-200' => $language !== 'en',
                                    ])
                                >
                                    <flux:icon.a-large-small variant="micro" />
                                </button>
                            </flux:tooltip>
                            <flux:tooltip content="Foreign" class="text-xs">
                                <button
                                    type="button"
                                    wire:click="$set('language', 'foreign')"
                                    @class([
                                        'rounded p-1.5 transition-colors',
                                        'bg-zinc-600 text-white' => $language === 'foreign',
                                        'text-zinc-400 hover:text-zinc-200' => $language !== 'foreign',
                                    ])
                                >
                                    <flux:icon.languages variant="micro" />
                                </button>
                            </flux:tooltip>
                            <flux:tooltip content="All" class="text-xs">
                                <button
                                    type="button"
                                    wire:click="$set('language', '')"
                                    @class([
                                        'rounded p-1.5 transition-colors',
                                        'bg-zinc-600 text-white' => $language === '',
                                        'text-zinc-400 hover:text-zinc-200' => $language !== '',
                                    ])
                                >
                                    <flux:icon.globe-americas variant="micro" />
                                </button>
                            </flux:tooltip>
                        </div>
                    </div>
                @endif
            </div>
            <flux:command.items
                class="min-h-0 flex-1 divide-y divide-zinc-700/70 overflow-y-auto bg-zinc-900/75 p-0 backdrop-blur-sm"
            >
                <x-slot:empty>
                    @if (strlen($query) >= 2 && ! SearchService::isImdbId($query))
                        <div class="space-y-1">
                            <p>{{ __('lundbergh.empty.search_no_results') }}</p>
                            <p>{{ __('lundbergh.empty.search_no_results_filter') }}</p>
                            <p>
                                Or go ahead and search by an
                                <flux:link href="https://www.imdb.com" external>IMDb</flux:link>
                                ID. That'd be great.
                            </p>
                        </div>
                    @else
                        {{ __('lundbergh.empty.search_prompt') }}
                    @endif
                </x-slot>

                @foreach ($this->results as $result)
                    <flux:command.item
                        wire:key="search-result-{{ $result['type'] }}-{{ $result['id'] }}"
                        as="a"
                        href="{{ $result['type'] === 'show' ? route('shows.show', $result['id']) : route('movies.show', $result['id']) }}"
                        wire:navigate
                        x-on:click="
                            $dispatch('modal-close', { name: 'search' })
                            $wire.clearSearch()
                        "
                        class="hover:bg-zinc-800/60 data-active:!bg-black"
                    >
                        <div class="flex w-full items-stretch gap-3">
                            <div class="flex w-32 shrink-0">
                                <x-artwork
                                    :model="$result['model']"
                                    type="logo"
                                    :alt="$result['title'] . ' logo'"
                                    :preview="true"
                                    class="h-full w-full overflow-hidden p-1"
                                />
                            </div>
                            <div class="flex min-w-0 flex-1 items-center gap-3 py-4 pe-3">
                                <div class="flex min-w-0 flex-1 flex-col gap-2">
                                    <div
                                        class="flex flex-wrap items-center gap-2 text-xs text-zinc-500 group-data-active/item:text-zinc-400"
                                    >
                                        @if ($result['yearLabel'])
                                            <span>{{ $result['yearLabel'] }}</span>
                                        @endif

                                        @if ($result['language'])
                                            <span>{{ $result['language'] }}</span>
                                        @endif

                                        @if ($result['status'])
                                            <x-show-status :status="$result['status']" />
                                        @endif

                                        @if ($result['runtime'])
                                            <span>{{ $result['runtime'] }}</span>
                                        @endif
                                    </div>

                                    <span class="truncate font-medium group-data-active/item:text-white">
                                        {{ $result['title'] }}
                                    </span>

                                    @if ($result['genres'] || $result['networkInfo'])
                                        <div class="flex flex-wrap items-center gap-2">
                                            @if ($result['genres'])
                                                @foreach ($result['genres'] as $genre)
                                                    <x-genre-badge :$genre size="sm" />
                                                @endforeach
                                            @endif

                                            @foreach ($result['networkInfo'] as $info)
                                                @if ($info['logoUrl'])
                                                    <flux:tooltip :content="$info['tooltip']" class="text-xs">
                                                        <img
                                                            src="{{ $info['logoUrl'] }}"
                                                            alt="{{ $info['tooltip'] }}"
                                                            class="h-4 w-auto object-contain"
                                                        />
                                                    </flux:tooltip>
                                                @else
                                                    <span
                                                        class="text-xs text-zinc-500 group-data-active/item:text-zinc-400"
                                                    >
                                                        {{ $info['tooltip'] }}
                                                    </span>
                                                @endif
                                            @endforeach
                                        </div>
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
                                        x-on:click="
                                            $dispatch('modal-close', { name: 'search' })
                                            $wire.clearSearch()
                                        "
                                        icon="arrow-right"
                                        size="sm"
                                    />
                                </div>
                            </div>
                        </div>
                    </flux:command.item>
                @endforeach

                @if ($this->results->isNotEmpty() && ! SearchService::isImdbId($query))
                    <div class="p-2">
                        <flux:callout>
                            <flux:callout.text>
                                Yeah… if you can't find what you're looking for, go ahead and try an
                                <flux:link href="https://www.imdb.com" external>IMDb</flux:link>
                                ID instead. That'd be great.
                            </flux:callout.text>
                        </flux:callout>
                    </div>
                @endif
            </flux:command.items>
        </flux:command>
    </flux:modal>
</div>
