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
            ->take(8)
            ->map(function (Movie|Show $item): array {
                $isShow = $item instanceof Show;

                $title = $isShow ? $item->name : $item->title;
                $originalTitle = null;

                if (
                    $this->shouldShowLanguage() &&
                    ! $isShow &&
                    $item->original_language?->value !== 'en' &&
                    $item->original_title &&
                    $item->original_title !== $title
                ) {
                    $originalTitle = $item->original_title;
                }

                return [
                    'type' => $isShow ? 'show' : 'movie',
                    'id' => $item->id,
                    'title' => $title,
                    'originalTitle' => $originalTitle,
                    'yearLabel' => $isShow ? Formatters::compactYearLabel($item) : null,
                    'releaseDate' => ! $isShow && $item->release_date ? $item->release_date->format('m/d/y') : null,
                    'productionCompany' =>
                        ! $isShow && ! empty($item->production_companies) ? $item->production_companies[0]['name'] : null,
                    'language' => $this->shouldShowLanguage()
                        ? ($isShow
                            ? $item->language?->getLabel()
                            : $item->original_language?->getLabel())
                        : null,
                    'status' => $item->status,
                    'runtime' => Formatters::runtimeFor($item),
                    'country' => $isShow
                        ? $item->network['country']['code'] ?? null
                        : (! empty($item->origin_country)
                            ? implode(', ', $item->origin_country)
                            : null),
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
        <x-command-panel
            name="search"
            panelClass="h-full bg-zinc-800/75 backdrop-blur-sm"
            itemsClass="divide-y divide-zinc-700/70 overflow-y-auto"
            :autoHighlightFirst="true"
        >
            <x-slot:header>
                <flux:input
                    wire:model.live.debounce.300ms="query"
                    icon="magnifying-glass"
                    placeholder="Search shows & movies and filter by language..."
                    autofocus
                    class="min-w-0 flex-1 bg-transparent"
                    class:input="search-input h-14 rounded-none border-0 bg-transparent text-white shadow-none placeholder-zinc-400"
                />
                @if (! SearchService::isImdbId($query))
                    <div
                        class="flex shrink-0 rounded-md bg-zinc-800 p-0.5 text-xs font-medium"
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
                @endif
            </x-slot>

            <x-slot:empty>
                <div class="px-3 py-4">
                    <x-lundbergh-bubble :with-margin="false">
                        @if (SearchService::isImdbId($query))
                            {{ __('lundbergh.empty.imdb_not_found') }}
                        @elseif (strlen($query) >= 2)
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
                    </x-lundbergh-bubble>
                </div>
            </x-slot>

            @foreach ($this->results as $result)
                <a
                    wire:key="search-result-{{ $result['type'] }}-{{ $result['id'] }}"
                    href="{{ $result['type'] === 'show' ? route('shows.show', $result['id']) : route('movies.show', $result['id']) }}"
                    wire:navigate
                    data-command-item
                    x-on:mouseenter="activate($el)"
                    x-on:mouseleave="deactivate($el)"
                    x-on:click="
                        $dispatch('modal-close', { name: 'search' })
                        $wire.clearSearch()
                    "
                    class="group/item flex h-auto w-full items-center rounded-none p-0 text-white hover:bg-zinc-700/60 focus:outline-hidden data-active:bg-zinc-700/60"
                >
                    <div class="flex w-full items-center gap-3 px-3 py-1">
                        <flux:icon
                            :name="$result['type'] === 'show' ? 'tv' : 'film'"
                            variant="mini"
                            class="shrink-0 text-zinc-400"
                        />

                        <div class="flex aspect-[1000/562] w-20 shrink-0 items-center">
                            <x-artwork
                                :model="$result['model']"
                                type="logo"
                                :alt="$result['title'] . ' logo'"
                                :preview="true"
                                class="h-full w-full overflow-hidden"
                            />
                        </div>

                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <p class="truncate text-base leading-snug text-white">
                                {{ $result['title'] }}
                                @if ($result['originalTitle'])
                                    <span class="text-[0.6875rem] text-zinc-500">
                                        {{ $result['originalTitle'] }}
                                    </span>
                                @endif
                            </p>
                            <div
                                class="flex min-w-0 items-center gap-x-[3px] overflow-hidden text-[0.6875rem] text-zinc-500 group-data-active/item:text-zinc-400"
                            >
                                @if ($result['type'] === 'show')
                                    @if ($result['yearLabel'])
                                        <span class="shrink-0 font-mono">{{ $result['yearLabel'] }}</span>
                                    @endif
                                @else
                                    @if ($result['releaseDate'])
                                        <span class="shrink-0 font-mono">{{ $result['releaseDate'] }}</span>
                                    @endif
                                @endif

                                @if ($result['status'])
                                    <x-media-status :status="$result['status']" />
                                @endif

                                @if ($result['runtime'])
                                    <flux:icon.dot variant="micro" class="shrink-0" />
                                    <span class="shrink-0 font-mono">{{ $result['runtime'] }}</span>
                                @endif

                                @if ($result['country'] || $result['language'])
                                    <flux:icon.dot variant="micro" class="shrink-0" />
                                @endif

                                @if ($result['country'])
                                    <span class="shrink-0">{{ $result['country'] }}</span>
                                @endif

                                @if ($result['language'])
                                    <span class="shrink-0">{{ $result['language'] }}</span>
                                @endif

                                @if ($result['genres'])
                                    <flux:icon.dot variant="micro" class="hidden shrink-0 sm:block" />
                                    <span class="hidden shrink-0 items-center gap-1 sm:inline-flex">
                                        @foreach ($result['genres'] as $genre)
                                            <flux:tooltip :content="\App\Enums\Genre::labelFor($genre)" class="text-xs">
                                                <x-dynamic-component
                                                    :component="'flux::icon.' . \App\Enums\Genre::iconFor($genre)"
                                                    variant="micro"
                                                    class="size-3 text-zinc-500 group-data-active/item:text-zinc-400"
                                                />
                                            </flux:tooltip>
                                        @endforeach
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center pe-1">
                            <flux:icon name="arrow-right" variant="mini" class="size-4 text-zinc-400" />
                        </div>
                    </div>
                </a>
            @endforeach

            <x-slot:footer>
                @if ($this->results->isNotEmpty() && ! SearchService::isImdbId($query))
                    <div class="border-b-0 px-3 py-2">
                        <x-lundbergh-bubble :with-margin="false" contentTag="div">
                            Yeah… if you can't find what you're looking for, go ahead and try an
                            <flux:link href="https://www.imdb.com" external class="text-xs">IMDb</flux:link>
                            ID instead. That'd be great.
                        </x-lundbergh-bubble>
                    </div>
                @endif
            </x-slot>
        </x-command-panel>
    </flux:modal>
</div>
