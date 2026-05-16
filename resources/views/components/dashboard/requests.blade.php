<?php

use App\Enums\EpisodeType;
use App\Enums\RequestItemStatus;
use App\Models\Episode;
use App\Models\Movie;
use App\Support\Concerns\WithPersistedPerPage;
use App\Support\EpisodeCode;
use App\Support\Formatters;
use App\Support\UserTime;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination, WithPersistedPerPage;

    /** @var array<int, string> */
    public array $statusFilters = [];

    protected function perPagePreferenceKey(): string
    {
        return 'dashboard.requests.per_page';
    }

    public function updatedStatusFilters(): void
    {
        $this->resetPage();
    }

    public function placeholder(): string
    {
        return <<<HTML
        <flux:card size="sm">
            <p class="font-semibold text-white">Requests</p>
            <div class="mt-3 space-y-3">
                <flux:skeleton class="h-4 w-full" />
                <flux:skeleton class="h-4 w-3/4" />
                <flux:skeleton class="h-4 w-5/6" />
                <flux:skeleton class="h-4 w-2/3" />
                <flux:skeleton class="h-4 w-full" />
            </div>
        </flux:card>
        HTML;
    }

    /**
     * @return Collection<int, array{status: RequestItemStatus, title: string, subtitle: string|null, updated_at: \Carbon\Carbon, url: string}>
     */
    #[Computed]
    public function filteredRows(): Collection
    {
        $rows = $this->allRows;

        if (! empty($this->statusFilters)) {
            $rows = $rows
                ->filter(fn (array $row): bool => in_array($row['status']->value, $this->statusFilters, true))
                ->values();
        }

        return $rows;
    }

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $filteredRows = $this->filteredRows;

        return new LengthAwarePaginator(
            items: $filteredRows->forPage($this->getPage(), $this->perPage),
            total: $filteredRows->count(),
            perPage: $this->perPage,
            currentPage: $this->getPage(),
            options: ['path' => request()->url()],
        );
    }

    /**
     * @return Collection<int, array{status: RequestItemStatus, title: string, subtitle: string|null, updated_at: \Carbon\Carbon, url: string}>
     */
    #[Computed]
    public function allRows(): Collection
    {
        $requests = auth()
            ->user()
            ->requests()
            ->with([
                'items.requestable' => function (MorphTo $morphTo): void {
                    $morphTo->morphWith([
                        Episode::class => ['show'],
                    ]);
                },
            ])
            ->latest()
            ->get();

        $items = $requests->flatMap(
            fn ($request) => $request->items->map(
                fn ($item) => [
                    'item' => $item,
                    'updated_at' => $item->updated_at,
                ],
            ),
        );

        $movieRows = $items->filter(fn ($entry) => $entry['item']->requestable instanceof Movie)->map(
            fn ($entry) => [
                'status' => $entry['item']->status,
                'title' => $entry['item']->requestable->title . ' (' . $entry['item']->requestable->year . ')',
                'subtitle' => null,
                'updated_at' => $entry['updated_at'],
                'url' => route('movies.show', $entry['item']->requestable),
            ],
        );

        $episodeEntries = $items->filter(fn ($entry) => $entry['item']->requestable instanceof Episode);

        $episodeRows = $this->consolidateEpisodes($episodeEntries);

        return $movieRows
            ->concat($episodeRows)
            ->sortByDesc('updated_at')
            ->values();
    }

    /**
     * @param  Collection<int, array{item: \App\Models\RequestItem, updated_at: \Carbon\Carbon}>  $entries
     * @return Collection<int, array{status: RequestItemStatus, title: string, subtitle: string|null, updated_at: \Carbon\Carbon, url: string}>
     */
    private function consolidateEpisodes(Collection $entries): Collection
    {
        if ($entries->isEmpty()) {
            return collect();
        }

        $byShow = $entries->groupBy(fn ($entry) => $entry['item']->requestable->show_id);

        // Prefetch all episodes for full-season detection
        $showIds = $byShow->keys()->all();
        $allShowEpisodes = Episode::whereIn('show_id', $showIds)
            ->where('type', '!=', EpisodeType::InsignificantSpecial)
            ->get()
            ->groupBy('show_id');

        return $byShow
            ->flatMap(function ($showEntries, $showId) use ($allShowEpisodes) {
                $show = $showEntries->first()['item']->requestable->show;
                $showUrl = route('shows.show', $show);
                $allEpisodesForShow = $allShowEpisodes->get($showId, collect());

                return $showEntries
                    ->groupBy(fn ($entry) => $entry['item']->requestable->season)
                    ->flatMap(function ($seasonEntries, $seasonNum) use ($show, $showUrl, $allEpisodesForShow) {
                        $allSeasonEpisodes = $allEpisodesForShow->where('season', $seasonNum);

                        return $this->buildRuns($seasonEntries, $allSeasonEpisodes)->map(function ($run) use (
                            $show,
                            $showUrl,
                            $allSeasonEpisodes,
                            $seasonNum,
                        ) {
                            $isFullSeason =
                                $allSeasonEpisodes->count() > 1 &&
                                $run['episodes']->count() === $allSeasonEpisodes->count() &&
                                $run['status'] !== null;

                            return [
                                'status' => $run['status'] ?? RequestItemStatus::Pending,
                                'title' => $show->name,
                                'subtitle' => $isFullSeason
                                    ? Formatters::formatSeason($seasonNum)
                                    : Formatters::formatRun($run['episodes']),
                                'updated_at' => $run['updated_at'],
                                'url' => $showUrl,
                            ];
                        });
                    });
            })
            ->values();
    }

    /**
     * Build episode runs split by status and contiguity.
     *
     * @param  Collection<int, array{item: \App\Models\RequestItem, updated_at: \Carbon\Carbon}>  $seasonEntries
     * @param  Collection<int, Episode>  $allSeasonEpisodes
     * @return Collection<int, array{episodes: Collection<int, Episode>, status: RequestItemStatus|null, updated_at: \Carbon\Carbon}>
     */
    private function buildRuns(Collection $seasonEntries, Collection $allSeasonEpisodes): Collection
    {
        $episodeData = $seasonEntries->keyBy(fn ($entry) => $entry['item']->requestable->id)->map(
            fn ($entry) => [
                'episode' => $entry['item']->requestable,
                'status' => $entry['item']->status,
                'updated_at' => $entry['updated_at'],
            ],
        );

        $requestedInOrder = $allSeasonEpisodes
            ->sort(fn ($a, $b) => EpisodeCode::compareForSorting($a->toArray(), $b->toArray()))
            ->values()
            ->filter(fn ($ep) => $episodeData->has($ep->id))
            ->values();

        if ($requestedInOrder->isEmpty()) {
            return collect();
        }

        return $requestedInOrder
            ->chunkWhile(
                fn (Episode $curr, int $key, Collection $chunk) => $episodeData[$curr->id]['status'] ===
                    $episodeData[$chunk->last()->id]['status'] && $this->isContiguous($chunk->last(), $curr),
            )
            ->map(
                fn (Collection $chunk) => [
                    'episodes' => $chunk->values(),
                    'status' => $episodeData[$chunk->first()->id]['status'],
                    'updated_at' => $chunk->max(fn ($ep) => $episodeData[$ep->id]['updated_at']),
                ],
            )
            ->values();
    }

    private function isContiguous(Episode $prev, Episode $curr): bool
    {
        $prevIsSpecial = $prev->type === EpisodeType::SignificantSpecial;
        $currIsSpecial = $curr->type === EpisodeType::SignificantSpecial;

        if ($prevIsSpecial !== $currIsSpecial) {
            return false;
        }

        return $curr->number === $prev->number + 1;
    }
};
?>

<div>
    @if ($this->allRows->isNotEmpty())
        <flux:card size="sm">
            <div class="flex items-center justify-between">
                <p class="font-semibold text-white">Requests</p>

                <flux:dropdown align="end">
                    <flux:button variant="subtle" size="sm" icon:trailing="funnel">
                        <span class="font-mono">{{ count($statusFilters) }}</span>
                    </flux:button>

                    <flux:menu>
                        <flux:menu.checkbox.group wire:model.live="statusFilters">
                            @foreach (RequestItemStatus::cases() as $status)
                                <flux:menu.checkbox value="{{ $status->value }}" keep-open>
                                    <span class="flex items-center gap-2">
                                        <flux:icon
                                            :name="$status->getIcon()"
                                            variant="mini"
                                            :class="$status->getIconColorClass()"
                                        />
                                        <span>{{ $status->getLabel() }}</span>
                                    </span>
                                </flux:menu.checkbox>
                            @endforeach
                        </flux:menu.checkbox.group>
                    </flux:menu>
                </flux:dropdown>
            </div>

            @if ($this->rows->isEmpty())
                <flux:text class="mt-2 text-zinc-500">
                    {{ __('lundbergh.dashboard.no_matching_requests') }}
                </flux:text>
            @else
                <x-dashboard.list>
                    @foreach ($this->rows as $row)
                        <x-dashboard.list-row
                            :href="$row['url']"
                            :wire-key="'request-row-' . $loop->index . '-' . $this->rows->currentPage()"
                        >
                            <x-slot:leading>
                                <flux:tooltip :content="$row['status']->getLabel()">
                                    <flux:icon
                                        :name="$row['status']->getIcon()"
                                        variant="mini"
                                        :class="'mt-0.5 shrink-0 sm:mt-0 ' . $row['status']->getIconColorClass()"
                                    />
                                </flux:tooltip>
                            </x-slot>

                            <span
                                class="block truncate font-serif tracking-wide sm:inline sm:overflow-visible sm:whitespace-normal"
                            >
                                {{ $row['title'] }}
                            </span>
                            @if ($row['subtitle'])
                                <span class="hidden text-zinc-500 sm:inline">·</span>
                                <span class="block text-sm text-zinc-400 sm:inline">
                                    {{ $row['subtitle'] }}
                                </span>
                            @endif

                            <x-slot:trailing>
                                <span class="shrink-0 text-right text-sm text-zinc-400">
                                    {{ Formatters::shortDate(UserTime::toUserTz($row['updated_at'])) }}
                                    <span class="hidden text-zinc-500 sm:inline">·</span>
                                    <span class="block text-zinc-500 sm:inline sm:text-xs">
                                        {{ Formatters::timeSince($row['updated_at']) }}
                                    </span>
                                </span>
                            </x-slot>
                        </x-dashboard.list-row>
                    @endforeach
                </x-dashboard.list>

                <flux:pagination :paginator="$this->rows" :per-page-options="[5, 10, 20]" per-page-model="perPage" />
            @endif
        </flux:card>
    @endif
</div>
