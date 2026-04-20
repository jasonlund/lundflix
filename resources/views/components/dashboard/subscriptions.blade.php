<?php

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use App\Support\AirDateTime;
use App\Support\Formatters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $view = 'upcoming';

    public function updatedView(): void
    {
        $this->resetPage();
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <flux:card size="sm">
            <div class="mt-3 space-y-3">
                <flux:skeleton class="h-4 w-full" />
                <flux:skeleton class="h-4 w-3/4" />
                <flux:skeleton class="h-4 w-5/6" />
            </div>
        </flux:card>
        HTML;
    }

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $allRows = $this->allRows;

        return new LengthAwarePaginator(
            items: $allRows->forPage($this->getPage(), 5),
            total: $allRows->count(),
            perPage: 5,
            currentPage: $this->getPage(),
            options: ['path' => request()->url()],
        );
    }

    /**
     * @return Collection<int, array{title: string, subtitle: string|null, detail: string|null, type: string, sort_date: \Carbon\Carbon|null}>
     */
    #[Computed]
    public function allRows(): Collection
    {
        return $this->view === 'recent' ? $this->recentRows() : $this->upcomingRows();
    }

    /**
     * @return Collection<int, array{title: string, subtitle: string|null, detail: string|null, type: string, sort_date: \Carbon\Carbon|null}>
     */
    private function upcomingRows(): Collection
    {
        $subscriptions = auth()
            ->user()
            ->subscriptions()
            ->with([
                'subscribable' => function (MorphTo $morphTo): void {
                    $morphTo->morphWith([
                        Show::class => [
                            'episodes' => fn ($q) => $q
                                ->where('airdate', '>=', today())
                                ->orderBy('airdate')
                                ->limit(3),
                        ],
                    ]);
                },
            ])
            ->latest()
            ->get();

        return $subscriptions
            ->map(function ($subscription): ?array {
                $subscribable = $subscription->subscribable;

                if ($subscribable instanceof Movie) {
                    return $this->buildUpcomingMovieRow($subscribable);
                }

                if ($subscribable instanceof Show) {
                    return $this->buildUpcomingShowRow($subscribable);
                }

                return null;
            })
            ->filter()
            ->sortBy(fn (array $row) => $row['sort_date'] ?? Carbon::create(9999, 12, 31))
            ->values();
    }

    /**
     * @return Collection<int, array{title: string, subtitle: string|null, detail: string|null, type: string, sort_date: \Carbon\Carbon|null}>
     */
    private function recentRows(): Collection
    {
        $subscriptions = auth()
            ->user()
            ->subscriptions()
            ->with([
                'subscribable' => function (MorphTo $morphTo): void {
                    $morphTo->morphWith([
                        Show::class => [
                            'episodes' => fn ($q) => $q
                                ->where('airdate', '<', today())
                                ->orderByDesc('airdate')
                                ->limit(1),
                        ],
                    ]);
                },
            ])
            ->latest()
            ->get();

        return $subscriptions
            ->map(function ($subscription): ?array {
                $subscribable = $subscription->subscribable;

                if ($subscribable instanceof Movie) {
                    return $this->buildRecentMovieRow($subscribable);
                }

                if ($subscribable instanceof Show) {
                    return $this->buildRecentShowRow($subscribable);
                }

                return null;
            })
            ->filter()
            ->sortByDesc(fn (array $row) => $row['sort_date'] ?? Carbon::create(1, 1, 1))
            ->values();
    }

    /**
     * @return array{title: string, subtitle: string|null, detail: string|null, type: string, sort_date: \Carbon\Carbon|null}
     */
    private function buildUpcomingMovieRow(Movie $movie): array
    {
        return [
            'title' => $movie->title . ' (' . $movie->year . ')',
            'subtitle' => null,
            'detail' => $movie->digital_release_date ? Formatters::timeUntil($movie->digital_release_date) : 'Unknown',
            'type' => 'movie',
            'sort_date' => $movie->digital_release_date,
        ];
    }

    /**
     * @return array{title: string, subtitle: string|null, detail: string|null, type: string, sort_date: \Carbon\Carbon|null}
     */
    private function buildUpcomingShowRow(Show $show): array
    {
        $episodes = $show->episodes;

        if ($episodes->isEmpty()) {
            return [
                'title' => $show->name,
                'subtitle' => null,
                'detail' => 'Unknown',
                'type' => 'show',
                'sort_date' => null,
            ];
        }

        $grouped = $episodes->groupBy(fn (Episode $ep): string => $ep->airdate->format('Y-m-d'));
        $firstGroup = $grouped->first();

        $subtitle = Formatters::formatRun($firstGroup);

        $firstEpisode = $firstGroup->first();
        $resolved = AirDateTime::resolve(
            $firstEpisode->airdate->format('Y-m-d'),
            $firstEpisode->airtime,
            $show->web_channel,
            $show->network,
        );
        $detail = Formatters::timeUntil($resolved);

        return [
            'title' => $show->name,
            'subtitle' => $subtitle,
            'detail' => $detail,
            'type' => 'show',
            'sort_date' => $firstEpisode->airdate,
        ];
    }

    /**
     * @return array{title: string, subtitle: string|null, detail: string|null, type: string, sort_date: \Carbon\Carbon|null}|null
     */
    private function buildRecentMovieRow(Movie $movie): ?array
    {
        $releaseDate = $movie->digital_release_date ?? $movie->release_date;

        if (! $releaseDate || $releaseDate->isFuture()) {
            return null;
        }

        return [
            'title' => $movie->title . ' (' . $movie->year . ')',
            'subtitle' => null,
            'detail' => Formatters::timeSince($releaseDate),
            'type' => 'movie',
            'sort_date' => $releaseDate,
        ];
    }

    /**
     * @return array{title: string, subtitle: string|null, detail: string|null, type: string, sort_date: \Carbon\Carbon|null}|null
     */
    private function buildRecentShowRow(Show $show): ?array
    {
        $episode = $show->episodes->first();

        if (! $episode) {
            return null;
        }

        return [
            'title' => $show->name,
            'subtitle' => Formatters::formatRun([$episode]),
            'detail' => Formatters::timeSince(AirDateTime::resolve(
                $episode->airdate->format('Y-m-d'),
                $episode->airtime,
                $show->web_channel,
                $show->network,
            )),
            'type' => 'show',
            'sort_date' => $episode->airdate,
        ];
    }
};
?>

<div>
    @if ($this->allRows->isNotEmpty())
        <flux:card size="sm">
            <div class="flex items-center justify-between">
                <p class="font-semibold text-white">Subscriptions</p>

                <flux:select wire:model.live="view" size="sm" class="max-w-fit" aria-label="Subscription view">
                    <flux:select.option value="upcoming">Upcoming</flux:select.option>
                    <flux:select.option value="recent">Recent</flux:select.option>
                </flux:select>
            </div>

            <flux:table :paginate="$this->rows">
                <flux:table.rows>
                    @foreach ($this->rows as $row)
                        <flux:table.row
                            wire:key="subscription-row-{{ $loop->index }}-{{ $this->rows->currentPage() }}"
                        >
                            <flux:table.cell variant="strong">
                                <div class="flex items-center gap-2">
                                    <flux:icon
                                        :name="$row['type'] === 'movie' ? 'film' : 'tv'"
                                        variant="mini"
                                        class="shrink-0 text-zinc-400"
                                    />
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
                                    {{ $row['detail'] }}
                                </span>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif
</div>
