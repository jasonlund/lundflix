<?php

use App\Enums\EpisodeType;
use App\Enums\RequestItemStatus;
use App\Models\Episode;
use App\Models\Movie;
use App\Support\EpisodeCode;
use App\Support\Formatters;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    /** @var array<int, string> */
    public array $statusFilters = [];

    public function updatedStatusFilters(): void
    {
        $this->resetPage();
    }

    public function placeholder(): string
    {
        $heading = e(__('lundbergh.dashboard.requests_heading'));

        return <<<HTML
        <flux:card size="sm">
            <flux:heading size="lg">{$heading}</flux:heading>
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
     * @return Collection<int, array{status: RequestItemStatus, title: string, subtitle: string|null, created_at: \Carbon\Carbon}>
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
            items: $filteredRows->forPage($this->getPage(), 5),
            total: $filteredRows->count(),
            perPage: 5,
            currentPage: $this->getPage(),
            options: ['path' => request()->url()],
        );
    }

    /**
     * @return Collection<int, array{status: RequestItemStatus, title: string, subtitle: string|null, created_at: \Carbon\Carbon}>
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
                    'created_at' => $request->created_at,
                ],
            ),
        );

        $movieRows = $items->filter(fn ($entry) => $entry['item']->requestable instanceof Movie)->map(
            fn ($entry) => [
                'status' => $entry['item']->status,
                'title' => $entry['item']->requestable->title . ' (' . $entry['item']->requestable->year . ')',
                'subtitle' => null,
                'created_at' => $entry['created_at'],
            ],
        );

        $episodeEntries = $items->filter(fn ($entry) => $entry['item']->requestable instanceof Episode);

        $episodeRows = $this->consolidateEpisodes($episodeEntries);

        return $movieRows
            ->concat($episodeRows)
            ->sortByDesc('created_at')
            ->values();
    }

    /**
     * @param  Collection<int, array{item: \App\Models\RequestItem, created_at: \Carbon\Carbon}>  $entries
     * @return Collection<int, array{status: RequestItemStatus, title: string, subtitle: string|null, created_at: \Carbon\Carbon}>
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
                $showName = $showEntries->first()['item']->requestable->show->name;
                $allEpisodesForShow = $allShowEpisodes->get($showId, collect());

                return $showEntries
                    ->groupBy(fn ($entry) => $entry['item']->requestable->season)
                    ->flatMap(function ($seasonEntries, $seasonNum) use ($showName, $allEpisodesForShow) {
                        $allSeasonEpisodes = $allEpisodesForShow->where('season', $seasonNum);

                        return $this->buildRuns($seasonEntries, $allSeasonEpisodes)->map(function ($run) use (
                            $showName,
                            $allSeasonEpisodes,
                            $seasonNum,
                        ) {
                            $isFullSeason =
                                $allSeasonEpisodes->count() > 1 &&
                                $run['episodes']->count() === $allSeasonEpisodes->count() &&
                                $run['status'] !== null;

                            return [
                                'status' => $run['status'] ?? RequestItemStatus::Pending,
                                'title' => $showName,
                                'subtitle' => $isFullSeason
                                    ? Formatters::formatSeason($seasonNum)
                                    : Formatters::formatRun($run['episodes']),
                                'created_at' => $run['created_at'],
                            ];
                        });
                    });
            })
            ->values();
    }

    /**
     * Build episode runs split by status and contiguity.
     *
     * @param  Collection<int, array{item: \App\Models\RequestItem, created_at: \Carbon\Carbon}>  $seasonEntries
     * @param  Collection<int, Episode>  $allSeasonEpisodes
     * @return Collection<int, array{episodes: Collection<int, Episode>, status: RequestItemStatus|null, created_at: \Carbon\Carbon}>
     */
    private function buildRuns(Collection $seasonEntries, Collection $allSeasonEpisodes): Collection
    {
        $episodeData = $seasonEntries->keyBy(fn ($entry) => $entry['item']->requestable->id)->map(
            fn ($entry) => [
                'episode' => $entry['item']->requestable,
                'status' => $entry['item']->status,
                'created_at' => $entry['created_at'],
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
                    'created_at' => $chunk->max(fn ($ep) => $episodeData[$ep->id]['created_at']),
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
                <flux:heading size="lg">{{ __('lundbergh.dashboard.requests_heading') }}</flux:heading>

                <flux:dropdown align="end">
                    <flux:button variant="subtle" size="sm" icon:trailing="funnel">
                        <span class="font-mono">{{ count($statusFilters) }}</span>
                    </flux:button>

                    <flux:menu>
                        <flux:menu.checkbox.group wire:model.live="statusFilters">
                            @foreach (RequestItemStatus::cases() as $status)
                                <flux:menu.checkbox value="{{ $status->value }}" keep-open>
                                    {{ $status->getLabel() }}
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
                <flux:table :paginate="$this->rows" class="mt-3">
                    <flux:table.rows>
                        @foreach ($this->rows as $row)
                            <flux:table.row
                                wire:key="request-row-{{ $loop->index }}-{{ $this->rows->currentPage() }}"
                            >
                                <flux:table.cell variant="strong">
                                    <div class="flex items-center gap-2">
                                        <flux:badge
                                            size="sm"
                                            :color="$row['status']->getFluxColor()"
                                            inset="top bottom"
                                        >
                                            {{ $row['status']->getLabel() }}
                                        </flux:badge>
                                        <span>
                                            {{ $row['title'] }}
                                            @if ($row['subtitle'])
                                                <span class="text-sm text-zinc-400">{{ $row['subtitle'] }}</span>
                                            @endif
                                        </span>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="text-right">
                                    <span class="text-sm text-zinc-400">
                                        {{ $row['created_at']->format('m/d/y') }}
                                    </span>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    @endif
</div>
