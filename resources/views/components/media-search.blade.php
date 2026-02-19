<?php

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

        return app(SearchService::class)
            ->search($this->query)
            ->take(10)
            ->map(function (Movie|Show $item): array {
                $isShow = $item instanceof Show;

                return [
                    'type' => $isShow ? 'show' : 'movie',
                    'id' => $item->id,
                    'title' => $isShow ? $item->name : $item->title,
                    'yearLabel' => $this->yearLabelFor($item),
                    'status' => $isShow ? $item->status : null,
                    'runtime' => $this->runtimeLabelFor($item),
                    'genres' => $item->genres ?? [],
                    'network' => $isShow ? $this->networkLabelFor($item) : null,
                    'model' => $item,
                ];
            });
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
            return $startYear . '-' . $show->ended->year;
        }

        if ($show->status === 'Running') {
            return $startYear . '-';
        }

        return (string) $startYear;
    }

    private function runtimeLabelFor(Show|Movie $item): ?string
    {
        if (! $item->runtime) {
            return null;
        }

        if ($item instanceof Show) {
            return $item->runtime . ' min';
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
    <flux:modal name="search" variant="bare" class="my-[10vh] w-full max-w-xl">
        <flux:command :filter="false" class="border-none shadow-lg">
            <flux:command.input
                wire:model.live.debounce.300ms="query"
                placeholder="Search shows & movies..."
                autofocus
                clearable
                closable
            />
            <flux:command.items
                class="min-h-0 flex-1 divide-y divide-zinc-700/70 overflow-y-auto bg-zinc-900/75 p-0 backdrop-blur-sm"
            >
                <x-slot:empty>
                    {{ strlen($query) >= 2 ? __('lundbergh.empty.search_no_results') : __('lundbergh.empty.search_prompt') }}
                </x-slot>

                @foreach ($this->results() as $result)
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

                                    <span class="truncate font-medium group-data-active/item:text-white">
                                        {{ $result['title'] }}
                                    </span>

                                    @if ($result['genres'] || $result['network'])
                                        <div class="flex flex-wrap items-center gap-2">
                                            @if ($result['genres'])
                                                @foreach ($result['genres'] as $genre)
                                                    <x-genre-badge :$genre size="sm" />
                                                @endforeach
                                            @endif

                                            @if ($result['network'])
                                                <span
                                                    class="text-xs text-zinc-500 group-data-active/item:text-zinc-400"
                                                >
                                                    {{ $result['network'] }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                <div @click.stop class="flex shrink-0 gap-1">
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
