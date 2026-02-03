<?php

use App\Enums\MovieArtwork;
use App\Enums\TvArtwork;
use App\Models\Movie;
use App\Models\Show;
use App\Services\SearchService;
use App\Support\Formatters;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component {
    public string $query = '';

    public function results(): Collection
    {
        if (strlen($this->query) < 2) {
            return collect();
        }

        $defaultShowLogoUrl = route('art', [
            'mediable' => 'show',
            'id' => 171,
            'type' => TvArtwork::HdClearLogo->value,
        ]);
        $defaultMovieLogoUrl = route('art', [
            'mediable' => 'movie',
            'id' => 75506,
            'type' => MovieArtwork::HdClearLogo->value,
        ]);

        return app(SearchService::class)
            ->search($this->query)
            ->take(10)
            ->map(
                fn ($item) => [
                    'type' => $item instanceof Show ? 'show' : 'movie',
                    'id' => $item->id,
                    'title' => $item instanceof Show ? $item->name : $item->title,
                    'yearLabel' => $this->yearLabelFor($item),
                    'status' => $item instanceof Show ? $item->status : null,
                    'runtime' => $this->runtimeLabelFor($item),
                    'genres' => $item->genres ?? [],
                    'network' => $item instanceof Show ? $this->networkLabelFor($item) : null,
                    'icon' => $item instanceof Show ? 'tv' : 'film',
                    'posterUrl' => $item instanceof Show ? $defaultShowLogoUrl : $defaultMovieLogoUrl,
                    'model' => $item instanceof Show ? null : $item,
                ],
            );
    }

    private function yearLabelFor(Show|Movie $item): ?string
    {
        if ($item instanceof Show) {
            return $this->showYearRange($item);
        }

        if (! $item->year) {
            return null;
        }

        return (string) $item->year;
    }

    private function showYearRange(Show $show): ?string
    {
        if (! $show->premiered) {
            return null;
        }

        $startYear = $show->premiered->year;

        if ($show->ended) {
            return $startYear.'-'.$show->ended->year;
        }

        if ($show->status === 'Running') {
            return $startYear.'-';
        }

        return (string) $startYear;
    }

    private function runtimeLabelFor(Show|Movie $item): ?string
    {
        if (! $item->runtime) {
            return null;
        }

        if ($item instanceof Show) {
            return $item->runtime.' min';
        }

        return Formatters::runtime($item->runtime);
    }

    private function networkLabelFor(Show $show): ?string
    {
        if (is_array($show->network) && isset($show->network['name'])) {
            $label = $show->network['name'];
            if (isset($show->network['country']['name'])) {
                $label .= " ({$show->network['country']['name']})";
            }

            return $label;
        }

        if (is_array($show->web_channel) && isset($show->web_channel['name'])) {
            return $show->web_channel['name'];
        }

        return null;
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
            class="flex h-full w-full flex-col overflow-hidden rounded-none border-0 bg-white/25 shadow-none backdrop-blur-sm dark:bg-zinc-900/25"
        >
            <flux:command.input
                wire:model.live.debounce.300ms="query"
                placeholder="Search shows & movies..."
                autofocus
                clearable
                closable
                class="h-14 border-0 bg-white/75 ps-12 pe-12 text-base backdrop-blur-sm dark:bg-zinc-900/75"
            />
            <flux:command.items
                class="min-h-0 flex-1 overflow-y-auto bg-white/75 p-0 backdrop-blur-sm dark:bg-zinc-900/75 divide-y divide-zinc-200/70 dark:divide-zinc-700/70"
            >
                @forelse ($this->results() as $result)
                    <flux:command.item
                        as="a"
                        href="{{ $result['type'] === 'show' ? route('shows.show', $result['id']) : route('movies.show', $result['id']) }}"
                        wire:navigate
                        x-on:click="
                            Flux.close('search')
                            $wire.clearSearch()
                        "
                        class="hover:bg-white/60 dark:hover:bg-zinc-800/60 data-active:!bg-black"
                    >
                        <div class="flex w-full items-stretch gap-3">
                            <div class="flex w-32 shrink-0">
                                <div class="h-full w-full overflow-hidden p-1">
                                    @if ($result['posterUrl'])
                                        <img
                                            src="{{ $result['posterUrl'] }}"
                                            alt="{{ $result['title'] }} poster"
                                            loading="lazy"
                                            onerror="this.classList.add('hidden'); this.nextElementSibling?.classList.remove('hidden')"
                                            class="h-full w-full object-contain"
                                        />
                                        <div
                                            class="hidden h-full w-full items-center justify-center text-zinc-500 dark:text-zinc-400"
                                        >
                                            <flux:icon :icon="$result['icon']" variant="mini" class="size-4" />
                                        </div>
                                    @else
                                        <div
                                            class="flex h-full w-full items-center justify-center text-zinc-500 dark:text-zinc-400"
                                        >
                                            <flux:icon :icon="$result['icon']" variant="mini" class="size-4" />
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="flex min-w-0 flex-1 items-center gap-3 py-4 pe-3">
                                <div class="flex min-w-0 flex-1 flex-col gap-2">
                                    <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500 group-data-active/item:text-zinc-400">
                                        @if ($result['yearLabel'])
                                            <span>{{ $result['yearLabel'] }}</span>
                                        @endif

                                        @if ($result['status'])
                                            <flux:badge
                                                size="sm"
                                                :color="$result['status'] === 'Running' ? 'green' : ($result['status'] === 'Ended' ? 'red' : 'zinc')"
                                            >
                                                {{ $result['status'] }}
                                            </flux:badge>
                                        @endif

                                        @if ($result['runtime'])
                                            <span>{{ $result['runtime'] }}</span>
                                        @endif
                                    </div>

                                    @if ($result['genres'] || $result['network'])
                                        <div class="flex flex-wrap items-center gap-2">
                                            @if ($result['genres'])
                                                @foreach ($result['genres'] as $genre)
                                                    <x-genre-badge :$genre size="sm" />
                                                @endforeach
                                            @endif

                                            @if ($result['network'])
                                                <span class="text-xs text-zinc-500 group-data-active/item:text-zinc-400">
                                                    {{ $result['network'] }}
                                                </span>
                                            @endif
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
                                            Flux.close('search')
                                            $wire.clearSearch()
                                        "
                                        icon="arrow-right"
                                        size="sm"
                                    />
                                </div>
                            </div>
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
