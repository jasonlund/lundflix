<?php

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use App\Support\AirDateTime;
use App\Support\Concerns\WithPersistedPerPage;
use App\Support\Formatters;
use App\Support\UserTime;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination, WithPersistedPerPage;

    public string $view = 'upcoming';

    protected function perPagePreferenceKey(): string
    {
        return 'dashboard.subscriptions.per_page';
    }

    public function updatedView(): void
    {
        $this->resetPage();
    }

    #[On('profile-updated')]
    public function refresh(): void
    {
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
            items: $allRows->forPage($this->getPage(), $this->perPage),
            total: $allRows->count(),
            perPage: $this->perPage,
            currentPage: $this->getPage(),
            options: ['path' => request()->url()],
        );
    }

    #[Computed]
    public function hasSubscriptions(): bool
    {
        return auth()
            ->user()
            ->subscriptions()
            ->exists();
    }

    /**
     * @return Collection<int, array{title: string, subtitle: string|null, detail: string|null, type: string, sort_date: \Carbon\Carbon|null, url: string, relative: string|null, recently_aired: bool}>
     */
    #[Computed]
    public function allRows(): Collection
    {
        return $this->view === 'recent' ? $this->recentRows() : $this->upcomingRows();
    }

    /**
     * @return Collection<int, array{title: string, subtitle: string|null, detail: string|null, type: string, sort_date: \Carbon\Carbon|null, url: string, relative: string|null, recently_aired: bool}>
     */
    private function upcomingRows(): Collection
    {
        [$cutoffSql, $cutoffBindings] = $this->airedCutoffSubquery();

        $subscriptions = auth()
            ->user()
            ->subscriptions()
            ->with([
                'subscribable' => function (MorphTo $morphTo) use ($cutoffSql, $cutoffBindings): void {
                    $morphTo->morphWith([
                        Show::class => [
                            'episodes' => fn ($q) => $q
                                ->whereRaw("airdate > {$cutoffSql}", $cutoffBindings)
                                ->orderBy('airdate')
                                ->limit(3),
                        ],
                    ]);
                },
            ])
            ->latest()
            ->get();

        return $subscriptions
            ->flatMap(function ($subscription): array {
                $subscribable = $subscription->subscribable;

                if ($subscribable instanceof Movie) {
                    $row = $this->buildUpcomingMovieRow($subscribable);

                    return $row ? [$row] : [];
                }

                if ($subscribable instanceof Show) {
                    return $this->buildUpcomingShowRows($subscribable);
                }

                return [];
            })
            ->sortBy(fn (array $row) => $row['sort_date']?->timestamp ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * @return Collection<int, array{title: string, subtitle: string|null, detail: string|null, type: string, sort_date: \Carbon\Carbon|null, url: string, relative: string|null, recently_aired: bool}>
     */
    private function recentRows(): Collection
    {
        [$cutoffSql, $cutoffBindings] = $this->airedCutoffSubquery();

        $subscriptions = auth()
            ->user()
            ->subscriptions()
            ->with([
                'subscribable' => function (MorphTo $morphTo) use ($cutoffSql, $cutoffBindings): void {
                    $morphTo->morphWith([
                        Show::class => [
                            'episodes' => fn ($q) => $q
                                ->whereRaw("airdate <= {$cutoffSql}", $cutoffBindings)
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
            ->sortByDesc(fn (array $row) => $row['sort_date']?->timestamp ?? 0)
            ->values();
    }

    /**
     * @return array{string, list<mixed>}
     */
    private function airedCutoffSubquery(): array
    {
        $overrides = AirDateTime::overrideCutoffs();
        $defaultCutoff = AirDateTime::effectiveAirDateCutoff(null)
            ->subDay()
            ->format('Y-m-d H:i:s');

        $whenClauses = [];
        $bindings = [];

        foreach ($overrides as $channelId => $cutoff) {
            $whenClauses[] = "WHEN json_extract(s.web_channel, '$.id') = ? THEN ?";
            $bindings[] = $channelId;
            $bindings[] = $cutoff->format('Y-m-d H:i:s');
        }

        $bindings[] = $defaultCutoff;

        $sql = '(SELECT CASE ' . implode(' ', $whenClauses) . ' ELSE ? END FROM shows s WHERE s.id = episodes.show_id)';

        return [$sql, $bindings];
    }

    /**
     * @return array{title: string, subtitle: string|null, detail: string|null, type: string, sort_date: \Carbon\Carbon|null, url: string, relative: string|null, recently_aired: bool}|null
     */
    private function buildUpcomingMovieRow(Movie $movie): ?array
    {
        $recentlyAired = $movie->digital_release_date?->isPast();

        if ($recentlyAired && $movie->digital_release_date->diffInHours(now(), absolute: true) >= 48) {
            return null;
        }

        return [
            'title' => $movie->title,
            'subtitle' => (string) $movie->year,
            'detail' => $movie->digital_release_date
                ? ($recentlyAired
                    ? $this->formatRecentlyAiredMovieDetail($movie->digital_release_date)
                    : $this->formatMovieDetail($movie->digital_release_date, true))
                : 'TBD',
            'type' => 'movie',
            'sort_date' => $movie->digital_release_date,
            'url' => route('movies.show', $movie),
            'relative' => $movie->digital_release_date
                ? ($recentlyAired
                    ? Formatters::timeSince($movie->digital_release_date)
                    : Formatters::relativeTime($movie->digital_release_date))
                : null,
            'recently_aired' => (bool) $recentlyAired,
        ];
    }

    /**
     * @return list<array{title: string, subtitle: string|null, detail: string|null, type: string, sort_date: \Carbon\Carbon|null, url: string, relative: string|null, recently_aired: bool}>
     */
    private function buildUpcomingShowRows(Show $show): array
    {
        $rows = [];

        $recentCandidates = $show
            ->episodes()
            ->where(
                'airdate',
                '>=',
                now()
                    ->subDays(3)
                    ->format('Y-m-d'),
            )
            ->where(
                'airdate',
                '<=',
                now()
                    ->addDay()
                    ->format('Y-m-d'),
            )
            ->orderByDesc('airdate')
            ->limit(3)
            ->get();

        foreach ($recentCandidates as $candidate) {
            $resolved = AirDateTime::resolve(
                $candidate->airdate->format('Y-m-d'),
                $candidate->airtime,
                $show->web_channel,
                $show->network,
            );

            if ($resolved->isPast() && $resolved->diffInHours(now(), absolute: true) < 48) {
                $rows[] = [
                    'title' => $show->name,
                    'subtitle' => Formatters::formatRun([$candidate]),
                    'detail' => $this->formatRecentlyAiredDetail($resolved),
                    'type' => 'show',
                    'sort_date' => $resolved,
                    'url' => route('shows.show', $show),
                    'relative' => Formatters::timeSince($resolved),
                    'recently_aired' => true,
                ];

                break;
            }
        }

        $episodes = $show->episodes;

        if ($episodes->isNotEmpty()) {
            $grouped = $episodes->groupBy(fn (Episode $ep): string => $ep->airdate->format('Y-m-d'));

            foreach ($grouped as $group) {
                $firstEpisode = $group->first();

                $resolved = AirDateTime::resolve(
                    $firstEpisode->airdate->format('Y-m-d'),
                    $firstEpisode->airtime,
                    $show->web_channel,
                    $show->network,
                );

                if ($resolved->isPast()) {
                    continue;
                }

                $rows[] = [
                    'title' => $show->name,
                    'subtitle' => Formatters::formatRun($group),
                    'detail' => $this->formatEpisodeDetail($resolved, true),
                    'type' => 'show',
                    'sort_date' => $resolved,
                    'url' => route('shows.show', $show),
                    'relative' => Formatters::relativeTime($resolved),
                    'recently_aired' => false,
                ];

                break;
            }
        }

        if (empty($rows)) {
            return [
                [
                    'title' => $show->name,
                    'subtitle' => null,
                    'detail' => 'TBD',
                    'type' => 'show',
                    'sort_date' => null,
                    'url' => route('shows.show', $show),
                    'relative' => null,
                    'recently_aired' => false,
                ],
            ];
        }

        return $rows;
    }

    /**
     * @return array{title: string, subtitle: string|null, detail: string|null, type: string, sort_date: \Carbon\Carbon|null, url: string, relative: string|null, recently_aired: bool}|null
     */
    private function buildRecentMovieRow(Movie $movie): ?array
    {
        $releaseDate = $movie->digital_release_date ?? $movie->release_date;

        if (! $releaseDate || $releaseDate->isFuture() || $releaseDate->diffInHours(now(), absolute: true) >= 48) {
            return null;
        }

        return [
            'title' => $movie->title,
            'subtitle' => (string) $movie->year,
            'detail' => $this->formatMovieDetail($releaseDate, false),
            'type' => 'movie',
            'sort_date' => $releaseDate,
            'url' => route('movies.show', $movie),
            'relative' => Formatters::relativeTime($releaseDate),
            'recently_aired' => false,
        ];
    }

    /**
     * @return array{title: string, subtitle: string|null, detail: string|null, type: string, sort_date: \Carbon\Carbon|null, url: string, relative: string|null, recently_aired: bool}|null
     */
    private function buildRecentShowRow(Show $show): ?array
    {
        $episode = $show->episodes->first();

        if (! $episode) {
            return null;
        }

        $resolved = AirDateTime::resolve(
            $episode->airdate->format('Y-m-d'),
            $episode->airtime,
            $show->web_channel,
            $show->network,
        );

        if ($resolved->diffInHours(now(), absolute: true) >= 48) {
            return null;
        }

        return [
            'title' => $show->name,
            'subtitle' => Formatters::formatRun([$episode]),
            'detail' => $this->formatEpisodeDetail($resolved, false),
            'type' => 'show',
            'sort_date' => $resolved,
            'url' => route('shows.show', $show),
            'relative' => Formatters::relativeTime($resolved),
            'recently_aired' => false,
        ];
    }

    private function formatMovieDetail(Carbon $releaseDate, bool $isUpcoming): string
    {
        if ($isUpcoming) {
            $userToday = Carbon::parse(now(UserTime::timezone())->format('Y-m-d'));
            $releaseDay = Carbon::parse($releaseDate->format('Y-m-d'));
            $daysAway = (int) $userToday->diffInDays($releaseDay, absolute: false);

            if ($daysAway >= 0 && $daysAway < 7) {
                return $this->shortWeekday($releaseDate);
            }
        }

        return $this->shortDate($releaseDate);
    }

    private function formatEpisodeDetail(Carbon $resolvedUtc, bool $isUpcoming): string
    {
        $userDate = UserTime::toUserTz($resolvedUtc);
        $time = $this->compactTime($userDate);

        if ($isUpcoming) {
            $hoursAway = (int) now()->diffInHours($resolvedUtc, absolute: true);

            if ($hoursAway < 24) {
                return $time;
            }

            if ($hoursAway < 168) {
                return $this->shortWeekday($userDate) . ' ' . $time;
            }
        }

        return $this->shortDate($userDate) . ' ' . $time;
    }

    private function compactTime(Carbon $date): string
    {
        $suffix = $date->format('a')[0];

        return (int) $date->format('i') === 0 ? $date->format('g') . $suffix : $date->format('g:i') . $suffix;
    }

    private function shortDate(Carbon $date): string
    {
        $format = $date->year === now()->year ? 'n/j' : 'n/j/y';

        return $date->format($format);
    }

    private function shortWeekday(Carbon $date): string
    {
        return substr($date->format('D'), 0, 2);
    }

    private function formatRecentlyAiredDetail(Carbon $resolvedUtc): string
    {
        $userDate = UserTime::toUserTz($resolvedUtc);
        $time = $this->compactTime($userDate);
        $userToday = now(UserTime::timezone())->format('Y-m-d');

        if ($userDate->format('Y-m-d') === $userToday) {
            return $time;
        }

        return $this->shortWeekday($userDate) . ' ' . $time;
    }

    private function formatRecentlyAiredMovieDetail(Carbon $releaseDate): string
    {
        $userToday = Carbon::parse(now(UserTime::timezone())->format('Y-m-d'));
        $releaseDay = Carbon::parse($releaseDate->format('Y-m-d'));

        if ($userToday->isSameDay($releaseDay)) {
            return $this->shortDate($releaseDate);
        }

        return $this->shortWeekday($releaseDate);
    }
};
?>

<div>
    <flux:card size="sm">
        <div class="flex items-center justify-between">
            <p class="font-semibold text-white">Subscriptions</p>

            @if ($this->hasSubscriptions)
                <flux:select wire:model.live="view" size="sm" class="max-w-fit" aria-label="Subscription view">
                    <flux:select.option value="upcoming">Upcoming</flux:select.option>
                    <flux:select.option value="recent">Recent</flux:select.option>
                </flux:select>
            @endif
        </div>

        @if (! $this->hasSubscriptions)
            <x-lundbergh-bubble :message="__('lundbergh.empty.subscriptions')" />
        @elseif ($this->rows->isEmpty())
            <x-lundbergh-bubble :message="__('lundbergh.dashboard.no_recent_subscriptions')" />
        @else
            <x-dashboard.list>
                @foreach ($this->rows as $row)
                    <x-dashboard.list-row
                        :href="$row['url']"
                        :wire-key="'subscription-row-' . $loop->index . '-' . $this->rows->currentPage()"
                        :muted="$row['detail'] === 'TBD'"
                    >
                        <x-slot:leading>
                            <flux:icon
                                :name="$row['type'] === 'movie' ? 'film' : 'tv'"
                                variant="mini"
                                class="mt-0.5 shrink-0 text-zinc-400 sm:mt-0"
                            />
                        </x-slot>

                        <span
                            class="block truncate font-serif tracking-wide sm:inline sm:overflow-visible sm:whitespace-normal"
                        >
                            {{ $row['title'] }}
                        </span>
                        @if ($row['subtitle'])
                            <span class="hidden text-zinc-500 sm:inline">·</span>
                            <span
                                class="{{ $row['type'] === 'movie' ? 'font-mono' : 'font-sans' }} block text-sm text-zinc-400 sm:inline"
                            >
                                {{ $row['subtitle'] }}
                            </span>
                        @endif

                        <x-slot:trailing>
                            <span class="shrink-0 text-right text-sm text-zinc-400">
                                @if ($row['recently_aired'])
                                    ({{ $row['detail'] }}
                                    <span class="hidden text-zinc-500 sm:inline">·</span>
                                    <span class="block text-zinc-500 sm:inline sm:text-xs">
                                        {{ $row['relative'] }})
                                    </span>
                                @else
                                    {{ $row['detail'] }}
                                    @if ($row['relative'])
                                        <span class="hidden text-zinc-500 sm:inline">·</span>
                                        <span class="block text-zinc-500 sm:inline sm:text-xs">
                                            {{ $row['relative'] }}
                                        </span>
                                    @endif
                                @endif
                            </span>
                        </x-slot>
                    </x-dashboard.list-row>
                @endforeach
            </x-dashboard.list>

            <flux:pagination
                :paginator="$this->rows"
                :per-page-options="[5, 10, 20]"
                per-page-model="perPage"
                class="-mx-4 px-4"
            />
        @endif
    </flux:card>
</div>
